import type { Metadata } from "next";
import "./styles.css";

export const metadata: Metadata = {
  title: "Картина по номерам",
  description: "Локальный генератор картины по номерам",
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="ru">
      <body>{children}</body>
    </html>
  );
}

