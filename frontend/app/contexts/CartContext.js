'use client';

import { createContext, useContext, useState, useCallback, useEffect, useMemo, useRef } from 'react';
import { normalizeQuantity, minQuantity } from '@/app/lib/units';
import { cartService } from '@/app/services';
import { useAuth } from '@/app/contexts/AuthContext';
import { productImage } from '@/app/lib/images';

const CartContext = createContext(null);
const STORAGE_KEY = 'axizan_cart_v1';

/**
 * Les anciens paniers pouvaient contenir des produits du catalogue statique
 * (product_id du type 'demo-ananas'). Ces lignes sont rejetees par l'API a la
 * commande : on les ecarte au chargement plutot que de faire echouer le tunnel.
 */
const isValidItem = (item) => item && Number.isFinite(Number(item.product_id)) && Number(item.quantity) > 0;

/** Ligne du serveur -> forme utilisee par l'interface. */
const fromServer = (row) => ({
  product_id: row.product.id,
  id: row.product.id,
  type: 'product',
  name: row.product.name,
  price: Number(row.product.base_price),
  image: productImage(row.product, 'thumb'),
  quantity: Number(row.quantity),
  price_type: row.product.price_type,
});

export function CartProvider({ children }) {
  const { user, loading: authLoading } = useAuth();

  const [items, setItems] = useState([]);
  const [hydrated, setHydrated] = useState(false);

  // Tant que la fusion n'a pas eu lieu, on ne renvoie rien au serveur : sinon
  // le panier local vide de la premiere image ecraserait le panier enregistre.
  const syncedUserId = useRef(null);

  useEffect(() => {
    if (typeof window === 'undefined') return;
    const stored = window.localStorage.getItem(STORAGE_KEY);
    if (stored) {
      try {
        const parsed = JSON.parse(stored);
        setItems((parsed.items || []).filter(isValidItem));
      } catch (error) {
        console.error('Impossible de charger le panier', error);
      }
    }
    setHydrated(true);
  }, []);

  useEffect(() => {
    if (typeof window === 'undefined') return;
    const payload = JSON.stringify({ items });
    window.localStorage.setItem(STORAGE_KEY, payload);
  }, [items]);

  /**
   * A la connexion : on pousse le panier local, puis on adopte le panier
   * serveur comme reference. Le client qui compose au bureau retrouve son
   * panier le soir sur son telephone.
   *
   * A la deconnexion : on oublie l'identifiant synchronise, sans vider le
   * panier local — le visiteur redevient anonyme, il ne perd pas ses articles.
   */
  useEffect(() => {
    if (authLoading || !hydrated) return;

    if (!user) {
      syncedUserId.current = null;
      return;
    }
    if (syncedUserId.current === user.id) return;

    syncedUserId.current = user.id;

    const payload = items
      .filter(isValidItem)
      .map((item) => ({ product_id: Number(item.product_id), quantity: Number(item.quantity) }));

    cartService
      .merge(payload)
      .then((res) => setItems(res.data.data.map(fromServer)))
      // Panier serveur injoignable : on continue avec le panier local plutot
      // que de bloquer les achats.
      .catch(() => {});
  }, [user, authLoading, hydrated, items]);

  /** Ecrit une ligne cote serveur, en silence : le panier local fait foi pour l'affichage. */
  const pushLine = useCallback(
    (productId, quantity) => {
      if (!user) return;
      cartService.put(Number(productId), Number(quantity)).catch(() => {});
    },
    [user]
  );

  const addItem = useCallback(
    (item) => {
      const productId = item.product_id ?? item.id;
      setItems((prevItems) => {
        const existingItem = prevItems.find((i) => i.product_id === productId);
        const next = existingItem
          ? prevItems.map((i) =>
              i.product_id === productId
                ? { ...i, quantity: normalizeQuantity(Number(i.quantity) + Number(item.quantity), i.price_type) }
                : i
            )
          : [...prevItems, { ...item, product_id: productId, id: productId }];

        pushLine(productId, next.find((i) => i.product_id === productId).quantity);
        return next;
      });
    },
    [pushLine]
  );

  const removeItem = useCallback(
    (itemId) => {
      setItems((prevItems) => prevItems.filter((i) => i.id !== itemId));
      if (user) cartService.remove(Number(itemId)).catch(() => {});
    },
    [user]
  );

  const updateQuantity = useCallback(
    (itemId, quantity) => {
      setItems((prevItems) => {
        const target = prevItems.find((i) => i.id === itemId);
        if (!target) return prevItems;

        if (Number(quantity) < minQuantity(target.price_type)) {
          if (user) cartService.remove(Number(itemId)).catch(() => {});
          return prevItems.filter((i) => i.id !== itemId);
        }

        const normalized = normalizeQuantity(quantity, target.price_type);
        pushLine(itemId, normalized);

        return prevItems.map((i) => (i.id === itemId ? { ...i, quantity: normalized } : i));
      });
    },
    [pushLine, user]
  );

  const clearCart = useCallback(() => {
    setItems([]);
    if (user) cartService.clear().catch(() => {});
  }, [user]);

  /**
   * Le panier ne connait QUE le prix des articles.
   *
   * Les frais de livraison dependent de la zone de l'adresse choisie, et la
   * taxe se calcule dessus : ces deux montants ne sont connus qu'au checkout,
   * et le total qui engage est celui que le serveur renvoie a la creation de
   * la commande. Afficher ici un total inventé serait un mensonge poli.
   */
  const subtotal = useMemo(
    () => items.reduce((sum, item) => sum + item.price * item.quantity, 0),
    [items]
  );

  return (
    <CartContext.Provider
      value={{
        items,
        addItem,
        removeItem,
        updateQuantity,
        clearCart,
        subtotal,
      }}
    >
      {children}
    </CartContext.Provider>
  );
}

export function useCart() {
  const context = useContext(CartContext);
  if (!context) {
    throw new Error('useCart must be used within CartProvider');
  }
  return context;
}
