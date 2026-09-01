import type { Metadata } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import "./globals.css";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

export const metadata: Metadata = {
  metadataBase: new URL("https://onelegalpro.com"),
  title: "OneLegalPro — Matter Desk for Thai law firms",
  description: "A focused workspace for law firms to organise clients, matters, responsibilities, tasks, and deadlines.",
  openGraph: {
    title: "OneLegalPro — A calmer way to run the matters that matter.",
    description: "A focused Matter Desk for Thai law firms. Founding-firm pilot availability by invitation.",
    url: "https://onelegalpro.com",
    siteName: "OneLegalPro",
    images: [{ url: "/og.png", width: 1731, height: 909, alt: "OneLegalPro — A calmer way to run the matters that matter." }],
    type: "website",
  },
  twitter: { card: "summary_large_image", title: "OneLegalPro — A calmer way to run the matters that matter.", description: "A focused Matter Desk for Thai law firms.", images: ["/og.png"] },
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body
        className={`${geistSans.variable} ${geistMono.variable} antialiased`}
      >
        {children}
      </body>
    </html>
  );
}
