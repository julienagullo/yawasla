import EmptyState from '../../components/EmptyState'
import { t } from '../../i18n/i18n'
import styles from './NewsroomPage.module.css'

// Bandeau et liste des annonces : textes génériques en attendant les données de l'organisme
export default function NewsroomPage() {
  return (
    <>
      <section className={styles.hero}>
        <h1>{t('public.hero.title')}</h1>
        <p>{t('public.hero.intro')}</p>
      </section>

      <section>
        <h2>{t('public.latest')}</h2>
        <EmptyState>{t('public.noAnnouncements')}</EmptyState>
      </section>
    </>
  )
}
