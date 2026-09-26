import { useState } from 'react'
import type { StatusResponse } from '../../api/status'
import SetupLayout from '../../layouts/SetupLayout'
import DatabaseStep from './steps/DatabaseStep'
import DoneStep from './steps/DoneStep'
import MigrationsStep from './steps/MigrationsStep'
import OrganizationStep from './steps/OrganizationStep'
import UserStep from './steps/UserStep'
import styles from './InstallWizard.module.css'
import { t } from '../../i18n/i18n'

const STEPS = ['database', 'migrations', 'organization', 'user', 'done'] as const

interface Props {
  status: StatusResponse
  // Recharge l'état depuis le back : c'est lui qui décide de l'étape suivante
  onProgress: () => void
}

export default function InstallWizard({ status, onProgress }: Props) {
  // La base est utilisable mais l'utilisateur n'a pas encore confirmé l'étape 1
  const [databaseAcknowledged, setDatabaseAcknowledged] = useState(false)
  const [done, setDone] = useState(false)

  const detected = status.step === 'migrations' && !databaseAcknowledged

  let current: number
  if (done) {
    current = 4
  } else if (status.status === 'config_required' || detected) {
    current = 0
  } else {
    current = { migrations: 1, organization: 2, user: 3 }[status.step ?? 'migrations']
  }

  // Étapes terminées : celles qui précèdent l'étape courante, et la dernière une fois l'installation finie
  const isDone = (index: number) => index < current || (done && index === STEPS.length - 1)

  return (
    <SetupLayout
      subtitle={
        status.target_version
          ? t('install.subtitleWithVersion', { version: status.target_version })
          : t('install.subtitle')
      }
    >
      <ol className={styles.stepper} aria-label={t('install.stepsLabel')}>
        {STEPS.map((step, index) => (
          <li
            key={step}
            aria-current={index === current ? 'step' : undefined}
            data-state={isDone(index) ? 'done' : undefined}
          >
            <span className={styles.dot}>{isDone(index) ? '✓' : index + 1}</span>
            <span className={styles.label}>{t(`install.steps.${step}`)}</span>
          </li>
        ))}
      </ol>

      {current === 0 && (
        <DatabaseStep
          status={status}
          detected={detected}
          onContinue={() => setDatabaseAcknowledged(true)}
          // Base créée ou configurée par l'assistant : inutile de redemander de confirmer sa détection
          onProgress={() => {
            setDatabaseAcknowledged(true)
            onProgress()
          }}
        />
      )}
      {current === 1 && (
        <MigrationsStep migrations={status.migrations ?? []} onProgress={onProgress} />
      )}
      {current === 2 && <OrganizationStep onProgress={onProgress} />}
      {/* Pas de rechargement ici : l'app serait déjà "installée" et l'écran final disparaîtrait */}
      {current === 3 && <UserStep onCreated={() => setDone(true)} />}
      {current === 4 && <DoneStep />}
    </SetupLayout>
  )
}
