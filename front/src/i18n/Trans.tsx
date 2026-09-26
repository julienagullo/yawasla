import { cloneElement, type ReactElement, type ReactNode } from 'react'
import { interpolate, resolve, type MessageKey, type Values } from './i18n'

// <nom>contenu</nom> : \1 impose une balise fermante identique à l'ouvrante
const TAG = /<(\w+)>([\s\S]*?)<\/\1>/

interface Props {
  k: MessageKey
  values?: Values
  // Balises autorisées dans la traduction, ex. { code: <code /> }
  components?: Record<string, ReactElement>
}

// Traduction contenant des balises : la phrase reste entière dans le JSON et chaque balise
// déclarée dans `components` devient un élément React (jamais de HTML interprété).
export default function Trans({ k, values, components = {} }: Props) {
  // split() avec groupes capturants : [texte, balise, contenu, texte, balise, contenu, …, texte]
  const parts = resolve(k, values).split(TAG)
  const nodes: ReactNode[] = []

  for (let i = 0; i < parts.length; i += 3) {
    // Interpolation après le découpage : une valeur contenant "<code>" reste du texte
    if (parts[i] !== '') {
      nodes.push(interpolate(parts[i], values))
    }

    if (i + 1 < parts.length) {
      const tag = parts[i + 1]
      const content = interpolate(parts[i + 2], values)
      const element = components[tag]

      // Balise non déclarée : affichée telle quelle, pour que l'oubli se voie
      nodes.push(element ? cloneElement(element, { key: i }, content) : `<${tag}>${content}</${tag}>`)
    }
  }

  return <>{nodes}</>
}
