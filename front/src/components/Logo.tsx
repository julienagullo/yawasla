import logo from '../assets/logo_yawasla.webp'

// Le dessin occupe environ 60 % de la hauteur de l'image : le ratio 1,5/1 avec object-fit: cover
// (voir .logo) recadre les marges transparentes du haut et du bas
const RATIO = 1.5

export default function Logo({ height = 48 }: { height?: number }) {
  return (
    <img
      className="logo"
      src={logo}
      alt="Yawasla"
      width={Math.round(height * RATIO)}
      height={height}
      draggable={false}
    />
  )
}
