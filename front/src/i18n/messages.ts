// Contrat des traductions : chaque fichier de locales/ doit fournir toutes ces clés
// (vérifié à la compilation dans i18n.ts). Aucune langue n'est la référence.

// Chaîne dont la forme dépend d'un nombre (values.count), choisie via Intl.PluralRules :
// "other" est obligatoire, les autres formes dépendent de la langue (l'arabe en a 6)
export type Plural = Partial<Record<Intl.LDMLPluralRule, string>> & { other: string }

export interface Messages {
  common: {
    loading: string
    retry: string
    continue: string
    copyright: string
    language: string
  }
  errors: {
    unknown: string
  }
  // Erreurs renvoyées par l'API : clé = code du back (snake_case, voir ApiException), "fields" = libellés
  // des noms de champs API reçus en paramètre `field`
  api: {
    database: {
      required_fields: string
      invalid_port: string
      invalid_characters: string
      invalid_name: string
      access_denied: string
      unreachable: string
      forbidden: string
      connection_failed: string
      create_failed: string
      env_not_writable: string
    }
    validation: {
      required: string
      too_long: string
      invalid_email: string
      invalid_domain: string
      password_too_short: string
      password_too_long: string
    }
    install: {
      organization_failed: string
      user_failed: string
    }
    fields: {
      name: string
      address: string
      city: string
      postal_code: string
      phone: string
      email: string
      first_name: string
      last_name: string
      display_name: string
    }
  }
  theme: {
    toLight: string
    toDark: string
  }
  app: {
    tagline: string
    unavailable: string
    updateRequired: string
  }
  public: {
    navLabel: string
    nav: {
      news: string
      media: string
    }
    hero: {
      title: string
      intro: string
    }
    latest: string
    noAnnouncements: string
    mediaTitle: string
    noMedia: string
    poweredBy: string
  }
  admin: {
    subtitle: string
    navLabel: string
    nav: {
      dashboard: string
    }
    viewSite: string
    dashboard: {
      title: string
      intro: string
    }
    login: {
      title: string
      email: string
      password: string
      submit: string
    }
  }
  install: {
    subtitle: string
    subtitleWithVersion: string
    stepsLabel: string
    steps: {
      database: string
      migrations: string
      organization: string
      user: string
      done: string
    }
    database: {
      detectedTitle: string
      detectedText: string
      unknownTitle: string
      unknownText: string
      create: string
      creating: string
      title: string
      intro: string
      host: string
      port: string
      name: string
      username: string
      password: string
      connecting: string
    }
    migrations: {
      title: string
      intro: Plural
      version: string
      submit: string
      submitting: string
    }
    organization: {
      title: string
      intro: string
      name: string
      address: string
      postalCode: string
      city: string
      phone: string
      email: string
      domain: string
      submitting: string
    }
    user: {
      title: string
      intro: string
      firstName: string
      lastName: string
      displayName: string
      email: string
      password: string
      confirmation: string
      passwordTooShort: string
      passwordMismatch: string
      submit: string
      submitting: string
    }
    done: {
      title: string
      intro: string
      link: string
    }
  }
}
