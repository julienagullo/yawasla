import { useState, type FormEvent } from 'react'
import { createOrganization } from '../../../api/install'
import { useAsyncAction } from '../../../hooks/useAsyncAction'
import { t } from '../../../i18n/i18n'

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
      <h1>{t('install.organization.title')}</h1>
      <p>{t('install.organization.intro')}</p>

      <form onSubmit={handleSubmit}>
        <fieldset disabled={pending}>
          <label>
            {t('install.organization.name')}
            <input required value={name} onChange={(e) => setName(e.target.value)} />
          </label>
          <label>
            {t('install.organization.address')}
            <input
              autoComplete="street-address"
              value={address}
              onChange={(e) => setAddress(e.target.value)}
            />
          </label>
          <div className="field-row">
            <label>
              {t('install.organization.postalCode')}
              <input
                autoComplete="postal-code"
                value={postalCode}
                onChange={(e) => setPostalCode(e.target.value)}
              />
            </label>
            <label>
              {t('install.organization.city')}
              <input
                autoComplete="address-level2"
                value={city}
                onChange={(e) => setCity(e.target.value)}
              />
            </label>
          </div>
          <div className="field-row">
            <label>
              {t('install.organization.phone')}
              <input type="tel" value={phone} onChange={(e) => setPhone(e.target.value)} />
            </label>
            <label>
              {t('install.organization.email')}
              <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} />
            </label>
          </div>
          <label>
            {t('install.organization.domain')}
            <input value={domain} onChange={(e) => setDomain(e.target.value)} />
          </label>
        </fieldset>

        {error && <p role="alert">{error}</p>}

        <button type="submit" disabled={pending}>
          {pending ? t('install.organization.submitting') : t('common.continue')}
        </button>
      </form>
    </>
  )
}
