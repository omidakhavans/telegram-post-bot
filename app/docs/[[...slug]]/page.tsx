import { notFound } from 'next/navigation';
import { source } from '@/lib/source';

export function generateStaticParams() { return source.generateParams(); }

export default async function DocsPageRoute({ params }: { params: Promise<{ slug?: string[] }> }) {
  const page = source.getPage((await params).slug);
  if (!page) notFound();
  const data = page.data as typeof page.data & { body: React.ComponentType };
  const MDX = data.body;
  return <article className="mx-auto max-w-3xl py-6 lg:py-12"><h1 className="text-4xl font-semibold tracking-[-.04em]">{data.title}</h1>{data.description ? <p className="mt-4 text-lg leading-8 text-slate-600">{data.description}</p> : null}<div className="prose-docs mt-10"><MDX /></div></article>;
}
