import { chooseLocale, isLocale, LOCALE_NAMES, t, type Locale } from '../i18n/i18n'
import { useLocale } from '../i18n/useLocale'
import styles from './LanguageSelect.module.css'

function GlobeIcon() {
  return (
    <svg
      className={styles.globe}
      width="1em"
      height="1em"
      viewBox="0 0 16 16"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.2"
      aria-hidden="true"
    >
      <circle cx="8" cy="8" r="7" />
      <ellipse cx="8" cy="8" rx="3" ry="7" />
      <path d="M1 8h14M2.2 4.5h11.6M2.2 11.5h11.6" />
    </svg>
  )
}

function ChevronIcon() {
  return (
    <svg
      className={styles.chevron}
      width="0.75em"
      height="0.75em"
      viewBox="0 0 16 16"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      aria-hidden="true"
    >
      <path d="M3 6l5 5 5-5" />
    </svg>
  )
}

// Sélecteur de langue : chaque langue est affichée dans sa propre langue
export default function LanguageSelect({ className }: { className?: string }) {
  const locale = useLocale()

  return (
    <div className={[styles.wrapper, className].filter(Boolean).join(' ')}>
      <GlobeIcon />
      <select
        className={styles.select}
        value={locale}
        aria-label={t('common.language')}
        onChange={(event) => {
          if (isLocale(event.target.value)) {
            chooseLocale(event.target.value)
          }
        }}
      >
        {(Object.keys(LOCALE_NAMES) as Locale[]).map((code) => (
          <option key={code} value={code} lang={code}>
            {LOCALE_NAMES[code]}
          </option>
        ))}
      </select>
      <ChevronIcon />
    </div>
  )
}
