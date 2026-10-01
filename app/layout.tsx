import type { Metadata } from 'next';
import './globals.css';

const repository = process.env.GITHUB_REPOSITORY ?? 'omidakhavans/Telegram-Post-Bot';
const [owner, name] = repository.split('/');
const siteUrl = process.env.GITHUB_ACTIONS === 'true' ? `https://${owner}.github.io/${name}/` : 'http://localhost:3000/';

export const metadata: Metadata = {
  title: { default: 'Telegram Post Bot', template: '%s · Telegram Post Bot' },
  description: 'A WordPress plugin for submitting draft posts through an authorized Telegram bot.',
  metadataBase: new URL(siteUrl),
  openGraph: { title: 'Telegram Post Bot', description: 'Submit WordPress draft posts through Telegram.', type: 'website' },
  icons: { icon: '/favicon.svg' },
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return <html lang="en"><body>{children}</body></html>;
}
