import { useState, type FormEvent } from 'react'
import { createOrganization } from '../../../api/install'
import { useAsyncAction } from '../../../hooks/useAsyncAction'

export default function OrganizationStep({ onProgress }: { onProgress: () => void }) {
  const { pending, error, run } = useAsyncAction()
  const [name, setName] = useState('')
  const [address, setAddress] = useState('')
  const [postalCode, setPostalCode] = useState('')
  const [city, setCity] = useState('')
  const [phone, setPhone] = useState('')
  const [email, setEmail] = useState('')
  // Pré-rempli avec le domaine sur lequel l'app est ouverte
  const [domain, setDomain] = useState(window.location.host)

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    void run(() =>
      createOrganization({
        name,
        address,
        city,
        postal_code: postalCode,
        phone,
        email,
        domain,
      }),
    ).then((ok) => ok && onProgress())
  }

  return (
    <>
      <h1>Votre organisme</h1>
      <p>Seul le nom est obligatoire, vous pourrez compléter le reste plus tard inchAllah.</p>

      <form onSubmit={handleSubmit}>
        <fieldset disabled={pending}>
          <label>
            Nom de l’organisme *
            <input required value={name} onChange={(e) => setName(e.target.value)} />
          </label>
          <label>
            Adresse
            <input
              autoComplete="street-address"
              value={address}
              onChange={(e) => setAddress(e.target.value)}
            />
          </label>
          <div className="field-row">
            <label>
              Code postal
              <input
                autoComplete="postal-code"
                value={postalCode}
                onChange={(e) => setPostalCode(e.target.value)}
              />
            </label>
            <label>
              Ville
              <input
                autoComplete="address-level2"
                value={city}
                onChange={(e) => setCity(e.target.value)}
              />
            </label>
          </div>
          <div className="field-row">
            <label>
              Téléphone
              <input type="tel" value={phone} onChange={(e) => setPhone(e.target.value)} />
            </label>
            <label>
              E-mail de contact
              <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} />
            </label>
          </div>
          <label>
            Domaine
            <input value={domain} onChange={(e) => setDomain(e.target.value)} />
          </label>
        </fieldset>

        {error && <p role="alert">{error}</p>}

        <button type="submit" disabled={pending}>
          {pending ? 'Enregistrement…' : 'Continuer'}
        </button>
      </form>
    </>
  )
}
