import { createBrowserRouter } from 'react-router-dom'
import { boot } from './boot'
import LocaleRoot from './LocaleRoot'
import PublicLayout from '../layouts/PublicLayout'
import AdminLayout from '../layouts/AdminLayout'
import NewsroomPage from '../pages/public/NewsroomPage'
import MediaListPage from '../pages/public/MediaListPage'
import LoginPage from '../pages/admin/LoginPage'
import DashboardPage from '../pages/admin/DashboardPage'

export const router = createBrowserRouter(
  [
    {
      element: <LocaleRoot />,
      children: [
        // Front-office : newsroom publique
        {
          element: <PublicLayout />,
          children: [
            { index: true, element: <NewsroomPage /> },
            { path: 'medias', element: <MediaListPage /> },
          ],
        },
        // Administration
        { path: 'admin/login', element: <LoginPage /> },
        {
          path: 'admin',
          element: <AdminLayout />,
          children: [{ index: true, element: <DashboardPage /> }],
        },
      ],
    },
  ],
  { basename: boot.basePath || '/' },
)
