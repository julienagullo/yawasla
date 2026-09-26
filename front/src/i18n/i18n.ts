import type { Messages, Plural } from './messages'
import enJson from './locales/en.json'
import frJson from './locales/fr.json'

// Vérification à la compilation : une clé de Messages absente d'une langue casse le build
const en: Messages = enJson
const fr: Messages = frJson

const catalogs = { fr, en }

export type Locale = keyof typeof catalogs
export type Values = Record<string, string | number>

export const DEFAULT_LOCALE: Locale = 'fr'

// Nom de chaque langue dans cette langue, pour le sélecteur (jamais traduit)
export const LOCALE_NAMES: Record<Locale, string> = { fr: 'Français', en: 'English' }

const LOCALE_STORAGE_KEY = 'yawasla-locale'

// Langues écrites de droite à gauche, à compléter quand l'une d'elles sera ajoutée
const RTL_LANGUAGES = new Set<string>(['ar', 'fa', 'he', 'ur'])

// Toutes les clés sous forme "install.database.title" (une feuille = une chaîne ou un Plural)
type Join<P extends string, K extends string> = P extends '' ? K : `${P}.${K}`
type Keys<T, P extends string = ''> = {
  [K in keyof T & string]: T[K] extends string | Plural ? Join<P, K> : Keys<T[K], Join<P, K>>
}[keyof T & string]

export type MessageKey = Keys<Messages>

// Langue courante : fixée au démarrage (main.tsx), modifiable via le sélecteur.
// Les composants se mettent à jour grâce à useLocale() (voir App.tsx)
let locale: Locale = DEFAULT_LOCALE
const listeners = new Set<() => void>()

// hasOwn et pas "in" : "toString" in {} vaut true
export function isLocale(value: string | null): value is Locale {
  return value !== null && Object.hasOwn(catalogs, value)
}

export function currentLocale(): Locale {
  return locale
}

export function subscribeLocale(listener: () => void): () => void {
  listeners.add(listener)

  return () => {
    listeners.delete(listener)
  }
}

export function setLocale(next: Locale) {
  locale = next
  document.documentElement.lang = next
  document.documentElement.dir = RTL_LANGUAGES.has(next) ? 'rtl' : 'ltr'
  listeners.forEach((listener) => listener())
}

// Choix explicite de l'utilisateur : appliqué et mémorisé pour les prochaines visites
export function chooseLocale(next: Locale) {
  setLocale(next)

  try {
    localStorage.setItem(LOCALE_STORAGE_KEY, next)
  } catch {
    // stockage indisponible (navigation privée, etc.) : le choix vaut pour la session en cours
  }
}

// Choix mémorisé, sinon première langue du navigateur prise en charge ("en-US" → "en"),
// sinon la langue par défaut
export function detectLocale(): Locale {
  let stored: string | null = null
  try {
    stored = localStorage.getItem(LOCALE_STORAGE_KEY)
  } catch {
    // stockage indisponible : on passe à la langue du navigateur
  }

  if (isLocale(stored)) {
    return stored
  }

  for (const tag of navigator.languages ?? [navigator.language]) {
    const language = tag.split('-')[0].toLowerCase()
    if (isLocale(language)) {
      return language
    }
  }

  return DEFAULT_LOCALE
}

function isPlural(node: unknown): node is Plural {
  return typeof node === 'object' && node !== null && typeof (node as Plural).other === 'string'
}

function lookup(key: string): string | Plural | undefined {
  let node: unknown = catalogs[locale]

  for (const part of key.split('.')) {
    if (typeof node !== 'object' || node === null) {
      return undefined
    }
    node = (node as Record<string, unknown>)[part]
  }

  return typeof node === 'string' || isPlural(node) ? node : undefined
}

// Pour les clés construites à l'exécution (ex. code d'erreur renvoyé par l'API)
export function hasMessage(key: string): key is MessageKey {
  return lookup(key) !== undefined
}

// Chaîne brute, forme plurielle choisie d'après values.count, sans interpolation (utilisée par <Trans>)
export function resolve(key: MessageKey, values?: Values): string {
  const entry = lookup(key)

  if (entry === undefined) {
    return key
  }

  if (typeof entry === 'string') {
    return entry
  }

  const form = new Intl.PluralRules(locale).select(Number(values?.count ?? 0))

  return entry[form] ?? entry.other
}

// Une seule passe : une valeur contenant "{autre}" n'est jamais remplacée à son tour.
// Une variable inconnue reste affichée telle quelle, pour que l'oubli se voie.
export function interpolate(text: string, values?: Values): string {
  if (!values) {
    return text
  }

  return text.replace(/\{(\w+)\}/g, (match, name: string) =>
    name in values ? String(values[name]) : match,
  )
}

export function t(key: MessageKey, values?: Values): string {
  return interpolate(resolve(key, values), values)
}
