import type { ReactNode } from 'react'
import styles from './EmptyState.module.css'

// Encart affiché quand une liste est vide
export default function EmptyState({ children }: { children: ReactNode }) {
  return <p className={styles.empty}>{children}</p>
}
