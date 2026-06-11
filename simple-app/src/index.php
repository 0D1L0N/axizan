<?php

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Axizán Simple</title>
    <style>
        body { margin: 0; font-family: Inter, system-ui, sans-serif; background: #f7f8fc; color: #1f2937; }
        header { background: #ff6b6b; color: #fff; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        header h1 { margin: 0; font-size: 1.3rem; }
        main { display: grid; grid-template-columns: 1.5fr 1fr; gap: 1.25rem; padding: 1.5rem 2rem; }
        section { background: #fff; border-radius: 1rem; padding: 1.25rem; box-shadow: 0 12px 30px rgba(15,23,42,0.08); }
        .product { border: 1px solid #e5e7eb; border-radius: .85rem; padding: 1rem; margin-bottom: 1rem; }
        .product h3 { margin: 0 0 .5rem; }
        .product p { margin: .5rem 0; color: #545b6a; }
        .product-footer { display: flex; justify-content: space-between; align-items: center; gap: .75rem; }
        button { border: none; border-radius: .75rem; padding: .7rem 1rem; cursor: pointer; font-weight: 600; }
        button.primary { background: #ff6b6b; color: white; }
        button.secondary { background: #eef2ff; color: #3730a3; }
        .cart-item { border-bottom: 1px solid #e5e7eb; padding: .75rem 0; display: grid; grid-template-columns: auto 1fr auto; gap: .75rem; align-items: center; }
        .cart-item:last-child { border-bottom: none; }
        .cart-item strong { display: block; }
        .cart-summary { margin-top: 1rem; }
        .field { margin-bottom: .85rem; }
        .field label { display: block; margin-bottom: .35rem; font-weight: 600; }
        .field input, .field textarea { width: 100%; padding: .9rem 1rem; border: 1px solid #d1d5db; border-radius: .75rem; }
        .notice { background: #ecfdf5; border: 1px solid #d1fae5; color: #166534; padding: .9rem 1rem; border-radius: .75rem; margin-top: 1rem; }
        .empty { color: #6b7280; text-align: center; padding: 2rem 0; }
        @media (max-width: 980px) { main { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <header>
        <div>
            <h1>Axizán Simple</h1>
            <div>Commande rapide - Apache + PHP + SQLite</div>
        </div>
    </header>
    <main>
        <section>
            <h2>Produits</h2>
            <div id="products"></div>
        </section>
        <section>
            <h2>Panier</h2>
            <div id="cart"></div>
            <div class="cart-summary" id="cartSummary"></div>
            <hr style="margin: 1rem 0; border: 0; border-top: 1px solid #e5e7eb;" />
            <h3>Informations</h3>
            <div class="field"><label for="customer_name">Nom</label><input id="customer_name" type="text" placeholder="Votre nom" /></div>
            <div class="field"><label for="phone">Téléphone</label><input id="phone" type="text" placeholder="+229 6000 0000" /></div>
            <div class="field"><label for="address">Adresse</label><textarea id="address" rows="3" placeholder="Adresse de livraison"></textarea></div>
            <button class="primary" id="placeOrder">Valider la commande</button>
            <div id="message"></div>
        </section>
    </main>
    <script>
        const apiBase = 'api.php';
        let cart = JSON.parse(localStorage.getItem('axizan_cart') || '[]');

        function formatPrice(value) {
            return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF' }).format(value);
        }

        async function fetchProducts() {
            const res = await fetch(`${apiBase}?action=products`);
            const json = await res.json();
            renderProducts(json.products);
        }

        function saveCart() {
            localStorage.setItem('axizan_cart', JSON.stringify(cart));
            renderCart();
        }

        function renderProducts(products) {
            const container = document.getElementById('products');
            container.innerHTML = '';
            products.forEach(product => {
                const productEl = document.createElement('div');
                productEl.className = 'product';
                productEl.innerHTML = `
                    <h3>${product.name}</h3>
                    <p>${product.description}</p>
                    <div class="product-footer">
                        <strong>${formatPrice(product.price)}</strong>
                        <button class="secondary">Ajouter</button>
                    </div>
                `;
                productEl.querySelector('button').addEventListener('click', () => addToCart(product));
                container.appendChild(productEl);
            });
        }

        function addToCart(product) {
            const existing = cart.find(item => item.id === product.id);
            if (existing) {
                existing.quantity += 1;
            } else {
                cart.push({ id: product.id, name: product.name, price: product.price, quantity: 1 });
            }
            saveCart();
        }

        function renderCart() {
            const container = document.getElementById('cart');
            container.innerHTML = '';
            if (!cart.length) {
                container.innerHTML = '<div class="empty">Votre panier est vide.</div>';
                document.getElementById('cartSummary').innerHTML = '';
                return;
            }
            let total = 0;
            cart.forEach(item => {
                total += item.price * item.quantity;
                const itemEl = document.createElement('div');
                itemEl.className = 'cart-item';
                itemEl.innerHTML = `
                    <div>
                        <strong>${item.name}</strong>
                        <span>${formatPrice(item.price)} x ${item.quantity}</span>
                    </div>
                    <div>${formatPrice(item.price * item.quantity)}</div>
                    <div>
                        <button class="secondary" data-id="${item.id}">-</button>
                        <button class="secondary" data-id="${item.id}" data-add>+</button>
                    </div>
                `;
                itemEl.querySelector('[data-id]').addEventListener('click', e => updateQuantity(item.id, -1));
                itemEl.querySelector('[data-add]').addEventListener('click', e => updateQuantity(item.id, 1));
                container.appendChild(itemEl);
            });
            document.getElementById('cartSummary').innerHTML = `<strong>Total :</strong> ${formatPrice(total)}`;
        }

        function updateQuantity(id, delta) {
            const item = cart.find(i => i.id === id);
            if (!item) return;
            item.quantity += delta;
            if (item.quantity <= 0) {
                cart = cart.filter(i => i.id !== id);
            }
            saveCart();
        }

        document.getElementById('placeOrder').addEventListener('click', async () => {
            const name = document.getElementById('customer_name').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const address = document.getElementById('address').value.trim();
            const message = document.getElementById('message');
            message.innerHTML = '';

            if (!name || !phone || !address || !cart.length) {
                message.innerHTML = '<div class="notice">Remplissez tous les champs et ajoutez au moins un produit.</div>';
                return;
            }

            const payload = {
                customer_name: name,
                phone,
                address,
                items: cart.map(item => ({ id: item.id, quantity: item.quantity, price: item.price }))
            };

            const res = await fetch(`${apiBase}?action=createOrder`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const json = await res.json();
            if (res.ok && json.success) {
                message.innerHTML = `<div class="notice">Commande enregistrée ! ID de commande : ${json.order_id}</div>`;
                cart = [];
                saveCart();
            } else {
                message.innerHTML = `<div class="notice">Erreur : ${json.error || 'Impossible de créer la commande.'}</div>`;
            }
        });

        fetchProducts();
        renderCart();
    </script>
</body>
</html>
