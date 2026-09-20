export default function DoneStep() {
  return (
    <>
      <h1>Installation terminée ✓</h1>
      <p>
        Tout s’est bien passé : la base de données, votre organisme et votre compte sont prêts.
        Vous pouvez maintenant vous connecter au back-office.
      </p>
      <a className="button" href={`${import.meta.env.BASE_URL}admin/login`}>
        Accéder au back-office
      </a>
    </>
  )
}
