import { createMDX } from 'fumadocs-mdx/next';

const isGitHubPages = process.env.GITHUB_ACTIONS === 'true';
const repository = process.env.GITHUB_REPOSITORY?.split('/')[1] ?? 'Telegram-Post-Bot';

/** @type {import('next').NextConfig} */
const nextConfig = {
  output: 'export',
  trailingSlash: true,
  reactStrictMode: true,
  basePath: isGitHubPages ? `/${repository}` : '',
  assetPrefix: isGitHubPages ? `/${repository}/` : undefined,
  images: { unoptimized: true },
};

export default createMDX()(nextConfig);
