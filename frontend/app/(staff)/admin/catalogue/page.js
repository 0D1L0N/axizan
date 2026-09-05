'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { adminService } from '@/app/services';
import { withRole } from '@/app/lib/auth-hoc';
import { useToast } from '@/app/contexts/ToastContext';
import Header from '@/app/components/Header';
import RemoteImage from '@/app/components/RemoteImage';
import { formatPrice, unitShort } from '@/app/lib/units';
import { buildPhotoUpload, humanSize } from '@/app/lib/resizeImage';
import {
  Archive,
  ArrowLeft,
  Camera,
  Check,
  Eye,
  EyeOff,
  ImageIcon,
  Loader2,
  Plus,
  RotateCcw,
  Search,
  Trash2,
  X,
} from 'lucide-react';

const UNITS = [
  { value: 'kg', label: 'au kilo' },
  { value: 'piece', label: 'à la pièce' },
  { value: 'cup', label: 'au tas / à la mesure' },
  { value: 'bottle', label: 'à la bouteille' },
  { value: 'jar', label: 'au pot' },
  { value: 'bag', label: 'au sac' },
];

const FILTERS = [
  { key: 'all', label: 'Tout le catalogue' },
  { key: 'no_photo', label: 'Sans photo' },
  { key: 'unavailable', label: 'Retirés de la vente' },
  { key: 'archived', label: 'Archivés' },
];

const FIELD =
  'mt-1.5 w-full rounded-xl border border-cream-300 bg-white px-3.5 py-2.5 text-[15px] text-ink-900 placeholder:text-ink-300';

/** Choix d'une photo : depuis le téléphone, ou dans la banque. */
function PhotoPicker({ product, onDone, onClose }) {
  const toast = useToast();

  const [library, setLibrary] = useState(null);
  const [busy, setBusy] = useState(false);
  const [saved, setSaved] = useState(null);

  useEffect(() => {
    adminService
      .getPhotoLibrary()
      .then((res) => setLibrary(res.data.data))
      .catch(() => setLibrary([]));
  }, []);

  const upload = async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    setBusy(true);
    try {
      const form = await buildPhotoUpload(file);
      const sent = form.get('photo').size + form.get('thumbnail').size;
      await adminService.uploadProductPhoto(product.id, form);
      setSaved({ before: file.size, after: sent });
      toast.success('Photo enregistrée.');
      onDone();
    } catch (err) {
      toast.fromApi(err, 'L’envoi a échoué.');
    } finally {
      setBusy(false);
    }
  };

  const assign = async (file) => {
    setBusy(true);
    try {
      await adminService.assignLibraryPhoto(product.id, file);
      toast.success('Photo assignée.');
      onDone();
      onClose();
    } catch (err) {
      toast.fromApi(err, 'L’assignation a échoué.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="mt-4 space-y-5 border-t border-cream-200 pt-5">
      <div>
        <p className="eyebrow text-ink-500">Depuis ce téléphone</p>
        <p className="mt-1.5 text-sm leading-6 text-ink-500">
          L’image est réduite ici avant l’envoi — une photo de 4 Mo part en 200 Ko environ.
        </p>
        <input
          type="file"
          accept="image/*"
          capture="environment"
          disabled={busy}
          onChange={upload}
          className="mt-3 block w-full text-sm file:mr-3 file:h-11 file:rounded-xl file:border file:border-cream-300 file:bg-white file:px-4 file:font-bold file:text-forest-800"
        />
        {saved && (
          <p className="mt-2 text-sm font-semibold text-forest-700">
            {humanSize(saved.before)} → {humanSize(saved.after)} envoyés.
          </p>
        )}
      </div>

      <div>
        <p className="eyebrow text-ink-500">Ou dans la banque d’images</p>
        {library === null ? (
          <p className="mt-2 flex items-center gap-2 text-sm text-ink-500">
            <Loader2 size={15} className="animate-spin" /> Chargement…
          </p>
        ) : library.length === 0 ? (
          <p className="mt-2 text-sm text-ink-500">La banque est vide.</p>
        ) : (
          <ul className="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-5">
            {library.map((item) => (
              <li key={item.file}>
                <button
                  type="button"
                  disabled={busy}
                  onClick={() => assign(item.file)}
                  title={item.used_by.length ? `Déjà utilisée par : ${item.used_by.join(', ')}` : undefined}
                  className="group relative block aspect-square w-full overflow-hidden rounded-xl border border-cream-300 transition hover:border-forest-600 disabled:opacity-50"
                >
                  <RemoteImage src={item.url} alt="" fill sizes="120px" className="object-cover" />
                  {item.used_by.length > 0 && (
                    // Éviter de coller la même photo d'étal sur cinq articles.
                    <span className="absolute inset-x-0 bottom-0 bg-ink-900/75 px-1 py-0.5 text-[10px] font-bold text-cream">
                      déjà utilisée
                    </span>
                  )}
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>

      {product.image_url && (
        <button
          type="button"
          disabled={busy}
          onClick={async () => {
            setBusy(true);
            try {
              await adminService.removeProductPhoto(product.id);
              toast.success('Photo retirée.');
              onDone();
            } catch (err) {
              toast.fromApi(err, 'Le retrait a échoué.');
            } finally {
              setBusy(false);
            }
          }}
          className="inline-flex items-center gap-2 text-sm font-semibold text-clay-600 underline underline-offset-4"
        >
          <Trash2 size={14} /> Retirer la photo actuelle
        </button>
      )}
    </div>
  );
}

/** Une ligne du catalogue, dépliable pour l'édition. */
function ProductRow({ product, categories, onChanged }) {
  const toast = useToast();

  const [panel, setPanel] = useState(null);
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState({
    name: product.name,
    description: product.description || '',
    category_id: String(product.category_id || ''),
    price_type: product.price_type,
    base_price: String(Math.round(Number(product.base_price))),
  });

  const archived = product.status === 'archived';

  const act = async (fn, message) => {
    setBusy(true);
    try {
      await fn();
      if (message) toast.success(message);
      onChanged();
    } catch (err) {
      toast.fromApi(err, 'L’action a échoué.');
    } finally {
      setBusy(false);
    }
  };

  const save = async (event) => {
    event.preventDefault();
    await act(
      () =>
        adminService.updateProduct(product.id, {
          ...form,
          category_id: Number(form.category_id),
          base_price: Number(form.base_price),
        }),
      'Article mis à jour.'
    );
    setPanel(null);
  };

  return (
    <li
      className={`rounded-card border bg-white shadow-card ${
        archived ? 'border-cream-300 opacity-60' : product.is_available ? 'border-cream-300' : 'border-clay-500/50'
      }`}
    >
      <div className="flex items-center gap-4 p-4">
        <div className="relative h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-cream-100">
          {product.thumbnail_url ? (
            <RemoteImage src={product.thumbnail_url} alt="" fill sizes="64px" className="object-cover" />
          ) : (
            <span className="grid h-full place-items-center text-ink-300">
              <ImageIcon size={20} />
            </span>
          )}
        </div>

        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <span className="font-semibold text-ink-900">{product.name}</span>
            {archived ? (
              <span className="rounded-full bg-cream-200 px-2 py-0.5 text-xs font-bold text-ink-500">
                Archivé
              </span>
            ) : (
              !product.is_available && (
                <span className="rounded-full bg-clay-200 px-2 py-0.5 text-xs font-bold text-clay-600">
                  Retiré de la vente
                </span>
              )
            )}
            {!product.image_url && !archived && (
              <span className="rounded-full bg-gold-200 px-2 py-0.5 text-xs font-bold text-forest-900">
                Sans photo
              </span>
            )}
          </div>
          <p className="mt-0.5 text-sm text-ink-500">
            {product.category?.name} · {formatPrice(Math.round(Number(product.base_price)))} /{' '}
            {unitShort(product.price_type)}
          </p>
        </div>

        <div className="flex shrink-0 items-center gap-1.5">
          {!archived && (
            <>
              <button
                onClick={() => setPanel(panel === 'photo' ? null : 'photo')}
                title="Photo"
                className="grid h-10 w-10 place-items-center rounded-full text-ink-500 transition hover:bg-cream-100 hover:text-forest-800"
              >
                <Camera size={17} />
              </button>
              <button
                onClick={() =>
                  act(
                    () => adminService.setProductAvailability(product.id, !product.is_available),
                    product.is_available ? `${product.name} retiré de la vente.` : `${product.name} remis en vente.`
                  )
                }
                disabled={busy}
                title={product.is_available ? 'Retirer de la vente' : 'Remettre en vente'}
                className="grid h-10 w-10 place-items-center rounded-full text-ink-500 transition hover:bg-cream-100 hover:text-forest-800"
              >
                {product.is_available ? <Eye size={17} /> : <EyeOff size={17} className="text-clay-600" />}
              </button>
            </>
          )}
          <button
            onClick={() => setPanel(panel === 'edit' ? null : 'edit')}
            className="rounded-full border border-cream-300 px-3.5 py-2 text-sm font-bold text-ink-900 transition hover:bg-cream-100"
          >
            {panel === 'edit' ? 'Fermer' : 'Modifier'}
          </button>
        </div>
      </div>

      {panel === 'photo' && (
        <div className="px-4 pb-4">
          <PhotoPicker product={product} onDone={onChanged} onClose={() => setPanel(null)} />
        </div>
      )}

      {panel === 'edit' && (
        <form onSubmit={save} className="space-y-3 border-t border-cream-200 bg-cream-100/50 px-4 py-4">
          <div className="grid gap-3 sm:grid-cols-2">
            <label className="block sm:col-span-2">
              <span className="eyebrow text-ink-500">Nom</span>
              <input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className={FIELD} />
            </label>
            <label className="block">
              <span className="eyebrow text-ink-500">Catégorie</span>
              <select
                value={form.category_id}
                onChange={(e) => setForm({ ...form, category_id: e.target.value })}
                className={FIELD}
              >
                {categories.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="block">
              <span className="eyebrow text-ink-500">Vendu</span>
              <select
                value={form.price_type}
                onChange={(e) => setForm({ ...form, price_type: e.target.value })}
                className={FIELD}
              >
                {UNITS.map((u) => (
                  <option key={u.value} value={u.value}>
                    {u.label}
                  </option>
                ))}
              </select>
            </label>
            <label className="block">
              <span className="eyebrow text-ink-500">Prix (XOF)</span>
              <input
                required
                type="number"
                min={0}
                step={1}
                value={form.base_price}
                onChange={(e) => setForm({ ...form, base_price: e.target.value })}
                className={FIELD}
              />
            </label>
            <label className="block sm:col-span-2">
              <span className="eyebrow text-ink-500">Description</span>
              <textarea
                rows={2}
                value={form.description}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
                className={FIELD}
              />
            </label>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <button
              type="submit"
              disabled={busy}
              className="inline-flex items-center gap-2 rounded-full bg-forest-800 px-5 py-2.5 text-sm font-bold text-cream transition hover:bg-forest-700 disabled:opacity-50"
            >
              {busy ? <Loader2 size={15} className="animate-spin" /> : <Check size={15} />} Enregistrer
            </button>

            {/* Jamais de suppression : des factures déjà remises pointent dessus. */}
            <button
              type="button"
              disabled={busy}
              onClick={() =>
                act(
                  () => adminService.setProductArchived(product.id, !archived),
                  archived ? 'Article restauré.' : 'Article archivé.'
                )
              }
              className="ml-auto inline-flex items-center gap-2 text-sm font-semibold text-ink-500 underline underline-offset-4"
            >
              {archived ? (
                <>
                  <RotateCcw size={14} /> Restaurer
                </>
              ) : (
                <>
                  <Archive size={14} /> Archiver
                </>
              )}
            </button>
          </div>
        </form>
      )}
    </li>
  );
}

/** Ajout d'un article. */
function NewProduct({ categories, onCreated, onClose }) {
  const toast = useToast();
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState({
    name: '',
    category_id: String(categories[0]?.id || ''),
    price_type: 'kg',
    base_price: '',
    description: '',
  });

  const submit = async (event) => {
    event.preventDefault();
    setBusy(true);
    try {
      await adminService.createProduct({
        ...form,
        category_id: Number(form.category_id),
        base_price: Number(form.base_price),
      });
      toast.success(`${form.name} ajouté au catalogue.`);
      onCreated();
      onClose();
    } catch (err) {
      toast.fromApi(err, 'La création a échoué.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <form onSubmit={submit} className="rounded-panel border border-cream-300 bg-white p-6 shadow-card">
      <div className="flex items-center justify-between gap-4">
        <h2 className="font-display text-xl font-semibold text-ink-900">Nouvel article</h2>
        <button type="button" onClick={onClose} className="text-ink-300 hover:text-ink-700">
          <X size={20} />
        </button>
      </div>

      <div className="mt-5 grid gap-3 sm:grid-cols-2">
        <label className="block sm:col-span-2">
          <span className="eyebrow text-ink-500">Nom</span>
          <input
            required
            autoFocus
            value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
            placeholder="Afitin (moutarde locale)"
            className={FIELD}
          />
        </label>
        <label className="block">
          <span className="eyebrow text-ink-500">Catégorie</span>
          <select
            value={form.category_id}
            onChange={(e) => setForm({ ...form, category_id: e.target.value })}
            className={FIELD}
          >
            {categories.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
        </label>
        <label className="block">
          <span className="eyebrow text-ink-500">Vendu</span>
          <select
            value={form.price_type}
            onChange={(e) => setForm({ ...form, price_type: e.target.value })}
            className={FIELD}
          >
            {UNITS.map((u) => (
              <option key={u.value} value={u.value}>
                {u.label}
              </option>
            ))}
          </select>
        </label>
        <label className="block">
          <span className="eyebrow text-ink-500">Prix (XOF)</span>
          <input
            required
            type="number"
            min={0}
            step={1}
            value={form.base_price}
            onChange={(e) => setForm({ ...form, base_price: e.target.value })}
            className={FIELD}
          />
        </label>
        <label className="block sm:col-span-2">
          <span className="eyebrow text-ink-500">Description</span>
          <textarea
            rows={2}
            value={form.description}
            onChange={(e) => setForm({ ...form, description: e.target.value })}
            className={FIELD}
          />
        </label>
      </div>

      <button
        type="submit"
        disabled={busy}
        className="mt-5 inline-flex items-center gap-2 rounded-full bg-forest-800 px-5 py-2.5 text-sm font-bold text-cream transition hover:bg-forest-700 disabled:opacity-50"
      >
        {busy ? <Loader2 size={15} className="animate-spin" /> : <Plus size={15} />} Ajouter
      </button>
      <p className="mt-3 text-sm text-ink-500">La photo se pose ensuite, depuis la liste.</p>
    </form>
  );
}

/**
 * Gestion du catalogue.
 *
 * Ce qui manquait : retirer un article introuvable sur le marché national,
 * corriger un prix, poser une photo. Tout passait par la base.
 */
function CataloguePage() {
  const toast = useToast();

  const [products, setProducts] = useState([]);
  const [categories, setCategories] = useState([]);
  const [meta, setMeta] = useState({});
  const [filter, setFilter] = useState('all');
  const [search, setSearch] = useState('');
  const [creating, setCreating] = useState(false);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminService.getCatalogue({
        filter: filter === 'all' ? undefined : filter,
        search: search.trim() || undefined,
      });
      setProducts(res.data.data);
      setMeta(res.data.meta);
    } catch (err) {
      toast.fromApi(err, 'Chargement impossible.');
    } finally {
      setLoading(false);
    }
  }, [filter, search, toast]);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    adminService
      .getCatalogueCategories()
      .then((res) => setCategories(res.data.data))
      .catch(() => setCategories([]));
  }, []);

  return (
    <>
      <Header />

      <div className="relative overflow-hidden bg-forest-900 cowrie-field grain">
        <div className="relative mx-auto max-w-5xl px-4 py-10 sm:py-12">
          <Link
            href="/admin"
            className="inline-flex items-center gap-2 text-sm font-bold text-cream/70 hover:text-cream"
          >
            <ArrowLeft size={15} /> Administration
          </Link>
          <p className="eyebrow mt-5 text-gold-500">Catalogue</p>
          <h1 className="mt-2 font-display text-display-sm text-cream">Ce que nous vendons</h1>
          <p className="mt-3 max-w-xl text-[15px] leading-7 text-cream-300">
            Un article retiré de la vente disparaît de la boutique, tout de suite.
          </p>

          <dl className="mt-8 flex flex-wrap gap-x-10 gap-y-4">
            {[
              ['Articles', meta.total || 0],
              ['Sans photo', meta.no_photo || 0],
              ['Retirés', meta.unavailable || 0],
              ['Archivés', meta.archived || 0],
            ].map(([label, value]) => (
              <div key={label}>
                <dt className="eyebrow text-cream-300/70">{label}</dt>
                <dd className="mt-1 font-display text-2xl font-semibold tabular-nums text-cream">{value}</dd>
              </div>
            ))}
          </dl>
        </div>
      </div>

      <main className="mx-auto max-w-5xl px-4 py-10">
        {creating && (
          <div className="mb-6">
            <NewProduct categories={categories} onCreated={load} onClose={() => setCreating(false)} />
          </div>
        )}

        <div className="mb-5 flex flex-wrap items-center gap-3">
          <div className="flex flex-wrap gap-2">
            {FILTERS.map((option) => (
              <button
                key={option.key}
                onClick={() => setFilter(option.key)}
                aria-pressed={filter === option.key}
                className={`rounded-full px-4 py-2 text-sm font-bold transition ${
                  filter === option.key
                    ? 'bg-forest-800 text-cream'
                    : 'border border-cream-300 bg-white text-ink-500 hover:border-forest-600'
                }`}
              >
                {option.label}
              </button>
            ))}
          </div>

          <label className="relative ml-auto">
            <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-ink-300" />
            <input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Chercher un article…"
              className="w-52 rounded-full border border-cream-300 bg-white py-2 pl-9 pr-3.5 text-sm text-ink-900 placeholder:text-ink-300"
            />
          </label>

          {!creating && (
            <button
              onClick={() => setCreating(true)}
              className="inline-flex items-center gap-2 rounded-full bg-gold-500 px-4 py-2 text-sm font-bold text-forest-900 transition hover:bg-gold-400"
            >
              <Plus size={15} /> Article
            </button>
          )}
        </div>

        {loading ? (
          <div className="flex items-center gap-2 rounded-card border border-cream-300 bg-white p-8 text-ink-500">
            <Loader2 size={18} className="animate-spin" /> Chargement…
          </div>
        ) : products.length === 0 ? (
          <div className="rounded-card border border-dashed border-cream-300 bg-white p-10 text-center">
            <p className="font-display text-lg font-semibold text-ink-900">Rien à afficher</p>
            <p className="mt-1 text-sm text-ink-500">Aucun article ne correspond à ce filtre.</p>
          </div>
        ) : (
          <ul className="space-y-3">
            {products.map((product) => (
              <ProductRow
                key={product.id}
                product={product}
                categories={categories}
                onChanged={load}
              />
            ))}
          </ul>
        )}
      </main>
    </>
  );
}

export default withRole(CataloguePage, ['admin']);
