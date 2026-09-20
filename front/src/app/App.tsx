import { RouterProvider } from 'react-router-dom'
import ThemeToggle from '../components/ThemeToggle'
import AppGate from './AppGate'
import { router } from './router'

export default function App() {
  return (
    <>
      <AppGate>
        <RouterProvider router={router} />
      </AppGate>
      <ThemeToggle />
    </>
  )
}
