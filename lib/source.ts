import { loader, type Source } from 'fumadocs-core/source';
import { docs } from '@/.source';

const mdxSource = docs.toFumadocsSource() as Source & { files: Source['files'] | (() => Source['files']) };

export const source = loader({
  baseUrl: '/docs',
  source: { files: typeof mdxSource.files === 'function' ? mdxSource.files() : mdxSource.files },
});
