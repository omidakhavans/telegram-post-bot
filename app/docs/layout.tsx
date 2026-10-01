import type { ReactNode } from 'react';
import Link from 'next/link';

const links = [
  ['Introduction', '/docs'],
  ['Getting started', '/docs/getting-started'],
  ['Architecture', '/docs/architecture'],
  ['Development', '/docs/development'],
];

export default function DocsLayout({ children }: { children: ReactNode }) {
  return <div className="min-h-screen bg-fog"><header className="border-b border-black/10"><div className="mx-auto flex max-w-7xl items-center justify-between px-5 py-5 lg:px-8"><Link href="/" className="font-semibold tracking-tight">Telegram Post Bot <span className="ml-2 font-mono text-xs text-slate-500">/ docs</span></Link><Link href="/" className="text-sm text-slate-500 hover:text-ink">Back to project</Link></div></header><div className="mx-auto grid max-w-7xl gap-10 px-5 py-10 lg:grid-cols-[230px_1fr] lg:px-8"><aside className="lg:sticky lg:top-8 lg:h-fit"><p className="mb-3 font-mono text-xs uppercase tracking-[.2em] text-electric">Contents</p><nav className="space-y-1">{links.map(([title, url]) => <Link key={url} href={url} className="block border-l border-black/10 px-3 py-2 text-sm text-slate-600 hover:border-electric hover:text-ink">{title}</Link>)}</nav></aside><div className="min-w-0">{children}</div></div></div>;
}
