import type { ReactNode } from 'react'
import Logo from '../components/Logo'

interface Props {
  // Sous-titre affiché sous le nom (ex. « Assistant d'installation »)
  subtitle?: string
  children: ReactNode
}

// Section centrée dans la page, utilisée par l'assistant d'installation et les écrans d'état
export default function SetupLayout({ subtitle, children }: Props) {
  return (
    <main className="setup">
      <section className="setup-card">
        <header className="setup-brand">
          <Logo />
          <div>
            <p className="setup-brand-name">Yawasla</p>
            <p className="setup-brand-tagline">
              {subtitle ?? 'Newsroom en ligne pour associations'}
            </p>
          </div>
        </header>
        {children}
      </section>
    </main>
  )
}
