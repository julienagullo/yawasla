import { runMigrations } from '../../../api/install'
import { useAsyncAction } from '../../../hooks/useAsyncAction'

interface Props {
  migrations: string[]
  onProgress: () => void
}

export default function MigrationsStep({ migrations, onProgress }: Props) {
  const { pending, error, run } = useAsyncAction()

  return (
    <>
      <h1>Création des tables</h1>
      <p>Les migrations suivantes vont être appliquées à la base de données.</p>

      <ul className="checklist">
        {migrations.map((version) => (
          <li key={version}>Version {version}</li>
        ))}
      </ul>

      {error && <p role="alert">{error}</p>}

      <button
        type="button"
        disabled={pending}
        onClick={() => void run(runMigrations).then((ok) => ok && onProgress())}
      >
        {pending ? 'Installation…' : 'Installer'}
      </button>
    </>
  )
}
