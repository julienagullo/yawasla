import { runMigrations } from '../../../api/install'
import { useAsyncAction } from '../../../hooks/useAsyncAction'
import styles from './MigrationsStep.module.css'
import { t } from '../../../i18n/i18n'

interface Props {
  migrations: string[]
  onProgress: () => void
}

export default function MigrationsStep({ migrations, onProgress }: Props) {
  const { pending, error, run } = useAsyncAction()

  return (
    <>
      <h1>{t('install.migrations.title')}</h1>
      <p>{t('install.migrations.intro', { count: migrations.length })}</p>

      <ul className={styles.checklist}>
        {migrations.map((version) => (
          <li key={version}>{t('install.migrations.version', { version })}</li>
        ))}
      </ul>

      {error && <p role="alert">{error}</p>}

      <button
        type="button"
        disabled={pending}
        onClick={() => void run(runMigrations).then((ok) => ok && onProgress())}
      >
        {pending ? t('install.migrations.submitting') : t('install.migrations.submit')}
      </button>
    </>
  )
}
