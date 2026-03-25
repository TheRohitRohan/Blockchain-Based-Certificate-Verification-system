import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.jsx'
import { AuthProvider } from './context/AuthContext.jsx'
import { DataProvider } from './context/DataContext.jsx'
import { CertificatesProvider } from './context/CertificatesContext.jsx'

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <AuthProvider>
      <DataProvider>
        <CertificatesProvider>
          <App />
        </CertificatesProvider>
      </DataProvider>
    </AuthProvider>
  </StrictMode>,
)
