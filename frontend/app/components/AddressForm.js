'use client';

import { useEffect, useState } from 'react';
import { addressService, logisticsService } from '@/app/services';
import { captureLocation, mapsLink, parseCoordinates } from '@/app/lib/geo';
import { Crosshair, ExternalLink, Loader2, MapPin } from 'lucide-react';

const EMPTY = {
  label: '',
  quartier: '',
  rue: '',
  numero_rue: '',
  landmark: '',
  detailed_instructions: '',
  delivery_zone_id: '',
  latitude: null,
  longitude: null,
  maps_url: '',
  is_default: false,
};

/**
 * Formulaire d'adresse, partage entre la page compte et le tunnel de commande.
 *
 * Trois façons de poser le point, parce qu'aucune ne marche partout :
 *  1. la position GPS du telephone (la plus precise, mais refusable) ;
 *  2. un lien de carte colle depuis Google Maps ou Plans ;
 *  3. quartier + repere ecrits a la main — le mode par defaut a Cotonou, ou
 *     beaucoup de rues n'ont ni nom ni numero.
 */
export default function AddressForm({ address = null, onSaved, onCancel }) {
  const [form, setForm] = useState(EMPTY);
  const [zones, setZones] = useState([]);
  const [saving, setSaving] = useState(false);
  const [locating, setLocating] = useState(false);
  const [errors, setErrors] = useState({});
  const [message, setMessage] = useState('');

  useEffect(() => {
    logisticsService
      .getZones()
      .then((res) => setZones(res.data.data))
      .catch(() => setZones([]));
  }, []);

  useEffect(() => {
    if (!address) {
      setForm(EMPTY);
      return;
    }
    setForm({
      ...EMPTY,
      ...address,
      rue: address.rue ?? '',
      numero_rue: address.numero_rue ?? '',
      landmark: address.landmark ?? '',
      detailed_instructions: address.detailed_instructions ?? '',
      maps_url: address.maps_url ?? '',
      delivery_zone_id: address.delivery_zone_id ?? '',
    });
  }, [address]);

  const set = (field, value) => setForm((current) => ({ ...current, [field]: value }));

  const handleLocate = async () => {
    setLocating(true);
    setMessage('');
    setErrors((e) => ({ ...e, location: undefined }));
    try {
      const { latitude, longitude, accuracy } = await captureLocation();
      setForm((current) => ({ ...current, latitude, longitude }));
      setMessage(`Position enregistrée (précision ~${accuracy} m).`);
    } catch (error) {
      setErrors((e) => ({ ...e, location: error.message }));
    } finally {
      setLocating(false);
    }
  };

  // Un lien colle contient souvent les coordonnees : on les recupere en silence.
  const handleMapsUrl = (value) => {
    set('maps_url', value);
    const parsed = parseCoordinates(value);
    if (parsed) {
      setForm((current) => ({ ...current, maps_url: value, ...parsed }));
      setMessage('Coordonnées extraites du lien.');
    }
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    setSaving(true);
    setErrors({});
    setMessage('');

    const payload = {
      ...form,
      delivery_zone_id: form.delivery_zone_id || null,
      maps_url: form.maps_url?.trim() || null,
      rue: form.rue?.trim() || null,
      numero_rue: form.numero_rue?.trim() || null,
      landmark: form.landmark?.trim() || null,
      detailed_instructions: form.detailed_instructions?.trim() || null,
    };

    try {
      const response = address
        ? await addressService.update(address.id, payload)
        : await addressService.create(payload);
      onSaved?.(response.data.data);
      if (!address) setForm(EMPTY);
    } catch (error) {
      setErrors(error.response?.data?.errors || { global: "L’adresse n’a pas pu être enregistrée." });
    } finally {
      setSaving(false);
    }
  };

  const preview = mapsLink(form.latitude, form.longitude, form.label);
  const fieldError = (name) => errors[name]?.[0];

  return (
    <form onSubmit={handleSubmit} className="space-y-5">
      {errors.global && (
        <p className="rounded-card bg-clay-200 px-4 py-3 font-semibold text-clay-600">{errors.global}</p>
      )}

      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Nom de l’adresse" required error={fieldError('label')}>
          <input
            required
            value={form.label}
            onChange={(e) => set('label', e.target.value)}
            placeholder="Maison, Bureau…"
            className={inputClass}
          />
        </Field>
        <Field label="Quartier" required error={fieldError('quartier')}>
          <input
            required
            value={form.quartier}
            onChange={(e) => set('quartier', e.target.value)}
            placeholder="Fidjrossè, Akpakpa, Ganhi…"
            className={inputClass}
          />
        </Field>
        <Field label="Rue" error={fieldError('rue')}>
          <input value={form.rue} onChange={(e) => set('rue', e.target.value)} className={inputClass} />
        </Field>
        <Field label="Numéro" error={fieldError('numero_rue')}>
          <input
            value={form.numero_rue}
            onChange={(e) => set('numero_rue', e.target.value)}
            className={inputClass}
          />
        </Field>
      </div>

      <Field
        label="Repère"
        hint="Ce que le livreur voit depuis la rue. Souvent plus utile que l’adresse elle-même."
        error={fieldError('landmark')}
      >
        <input
          value={form.landmark}
          onChange={(e) => set('landmark', e.target.value)}
          placeholder="En face de la pharmacie Sainte-Rita, portail vert"
          className={inputClass}
        />
      </Field>

      <Field label="Zone de livraison" hint="Elle détermine les frais de livraison." error={fieldError('delivery_zone_id')}>
        <select
          value={form.delivery_zone_id}
          onChange={(e) => set('delivery_zone_id', e.target.value)}
          className={inputClass}
        >
          <option value="">Je ne sais pas</option>
          {zones.map((zone) => (
            <option key={zone.id} value={zone.id}>
              {zone.name} — {Number(zone.base_delivery_fee).toLocaleString('fr-FR')} FCFA
            </option>
          ))}
        </select>
      </Field>

      {/* ---- Localisation ---- */}
      <div className="rounded-panel border border-ink-900/8 bg-cream-100 p-5">
        <p className="font-display text-xl font-semibold text-forest-800">Point sur la carte</p>
        <p className="mt-1.5 text-sm leading-6 text-ink-500">
          Facultatif, mais c’est ce qui évite l’appel « je suis où&nbsp;? » le jour de la livraison.
        </p>

        <div className="mt-4 flex flex-wrap gap-3">
          <button
            type="button"
            onClick={handleLocate}
            disabled={locating}
            className="inline-flex items-center gap-2 rounded-full bg-forest-800 px-5 py-3 text-sm font-bold text-cream transition hover:bg-forest-700 disabled:opacity-60"
          >
            {locating ? <Loader2 size={16} className="animate-spin" /> : <Crosshair size={16} />}
            {locating ? 'Localisation…' : 'Utiliser ma position'}
          </button>

          {preview && (
            <a
              href={preview}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-2 rounded-full border border-forest-800/20 px-5 py-3 text-sm font-bold text-forest-800 transition hover:bg-cream-200"
            >
              <ExternalLink size={16} /> Vérifier sur la carte
            </a>
          )}
        </div>

        {form.latitude != null && form.longitude != null && (
          <p className="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-forest-600">
            <MapPin size={15} />
            {Number(form.latitude).toFixed(5)}, {Number(form.longitude).toFixed(5)}
            <button
              type="button"
              onClick={() => setForm((c) => ({ ...c, latitude: null, longitude: null }))}
              className="ml-1 text-ink-300 underline hover:text-clay-500"
            >
              retirer
            </button>
          </p>
        )}
        {errors.location && <p className="mt-3 text-sm font-semibold text-clay-600">{errors.location}</p>}

        <div className="mt-4">
          <Field
            label="Ou coller un lien de carte"
            hint="Depuis Google Maps ou Plans : « Partager » puis « Copier le lien »."
            error={fieldError('maps_url')}
          >
            <input
              type="url"
              value={form.maps_url}
              onChange={(e) => handleMapsUrl(e.target.value)}
              placeholder="https://maps.app.goo.gl/…"
              className={inputClass}
            />
          </Field>
        </div>

        {message && <p className="mt-3 text-sm font-semibold text-forest-600">{message}</p>}
      </div>

      <Field label="Instructions pour le livreur" error={fieldError('detailed_instructions')}>
        <textarea
          rows={3}
          value={form.detailed_instructions}
          onChange={(e) => set('detailed_instructions', e.target.value)}
          placeholder="Sonner deux fois, appeler en arrivant, laisser au gardien…"
          className={`${inputClass} resize-y`}
        />
      </Field>

      <label className="flex items-center gap-3 font-semibold text-ink-700">
        <input
          type="checkbox"
          checked={!!form.is_default}
          onChange={(e) => set('is_default', e.target.checked)}
          className="h-5 w-5 accent-[#12372A]"
        />
        Adresse de livraison par défaut
      </label>

      <div className="flex flex-wrap gap-3 pt-1">
        <button
          type="submit"
          disabled={saving}
          className="inline-flex items-center gap-2 rounded-full bg-gold-500 px-6 py-3.5 font-bold text-forest-950 shadow-glow transition hover:bg-gold-400 disabled:opacity-60"
        >
          {saving && <Loader2 size={16} className="animate-spin" />}
          {address ? 'Enregistrer les modifications' : 'Ajouter cette adresse'}
        </button>
        {onCancel && (
          <button
            type="button"
            onClick={onCancel}
            className="rounded-full border border-ink-900/12 px-6 py-3.5 font-bold text-ink-700 transition hover:bg-cream-200"
          >
            Annuler
          </button>
        )}
      </div>
    </form>
  );
}

const inputClass =
  'w-full rounded-card border border-ink-900/12 bg-white px-4 py-3 font-medium text-ink-900 outline-none transition placeholder:text-ink-300 focus:border-forest-600';

function Field({ label, hint, required, error, children }) {
  return (
    <label className="block">
      <span className="block text-sm font-bold text-ink-700">
        {label}
        {required && <span className="text-clay-500"> *</span>}
      </span>
      {hint && <span className="mt-0.5 block text-sm leading-6 text-ink-500">{hint}</span>}
      <span className="mt-2 block">{children}</span>
      {error && <span className="mt-1.5 block text-sm font-semibold text-clay-600">{error}</span>}
    </label>
  );
}
