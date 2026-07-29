import type { NextConfig } from "next";

const laravelOrigin = process.env.LARAVEL_API_ORIGIN ?? "http://localhost:8080";

const nextConfig: NextConfig = {
  images: {
    remotePatterns: [
      { protocol: 'https', hostname: '**' },
      { protocol: 'http', hostname: '**' },
    ],
  },
  async rewrites() {
    return [
      {
        source: "/api/:path*",
        destination: `${laravelOrigin}/api/:path*`,
      },
      {
        source: "/auth/:path*",
        destination: `${laravelOrigin}/auth/:path*`,
      },
    ];
  },
};

export default nextConfig;
