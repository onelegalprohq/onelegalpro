import type { MetadataRoute } from "next";
export default function sitemap(): MetadataRoute.Sitemap { return ["", "/privacy", "/terms"].map(path => ({ url: `https://onelegalpro.com${path}`, lastModified: new Date("2026-09-15"), changeFrequency: path ? "yearly" : "monthly", priority: path ? 0.5 : 1 })); }
