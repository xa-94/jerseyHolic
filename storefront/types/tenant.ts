export interface StoreTheme {
  primary_color: string
  logo_url: string
}

export interface StoreConfig {
  id: number
  domain: string
  name: string
  status: string
  languages: string[]
  default_language: string
  currencies: string[]
  default_currency: string
  theme: StoreTheme
  category_l1_id: number
  market: string
  rtl: boolean
}
