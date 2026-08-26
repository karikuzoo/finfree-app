/**
 * Daftar syarat kata sandi yang tercentang langsung saat pengguna mengetik.
 *
 * Kenapa perlu: backend mengembalikan satu pesan per field, jadi pengguna yang
 * kata sandinya melanggar empat aturan hanya melihat satu keluhan sekaligus.
 * Memperbaiki panjangnya, kirim, baru tahu harus ada huruf besar, kirim lagi,
 * baru tahu harus ada simbol — empat putaran untuk satu field.
 *
 * Daftar ini HANYA petunjuk. Yang menentukan diterima atau tidak tetap validasi
 * backend (Password::defaults() di AppServiceProvider); keduanya harus tetap
 * sama isinya bila aturannya diubah.
 */
const syarat = [
    { label: 'Minimal 6 karakter', uji: (v) => v.length >= 6 },
    { label: 'Ada huruf besar', uji: (v) => /[A-Z]/.test(v) },
    { label: 'Ada huruf kecil', uji: (v) => /[a-z]/.test(v) },
    { label: 'Ada angka', uji: (v) => /\d/.test(v) },
    { label: 'Ada simbol', uji: (v) => /[^A-Za-z0-9]/.test(v) },
];

export default function PasswordRequirements({ value = '', className = '' }) {
    // Belum diketik apa-apa: jangan tampilkan lima tanda silang merah sebagai
    // sambutan. Itu terbaca seperti menegur orang sebelum ia berbuat apa pun.
    if (!value) {
        return (
            <p className={'text-xs text-text-muted ' + className}>
                Minimal 6 karakter, memuat huruf besar, huruf kecil, angka, dan
                simbol.
            </p>
        );
    }

    return (
        <ul className={'flex flex-wrap gap-x-4 gap-y-1 ' + className}>
            {syarat.map((s) => {
                const lolos = s.uji(value);

                return (
                    <li
                        key={s.label}
                        className={
                            'flex items-center gap-1.5 text-xs transition-colors duration-150 ' +
                            (lolos ? 'text-state-success' : 'text-text-muted')
                        }
                    >
                        <svg
                            className="h-3.5 w-3.5 shrink-0"
                            viewBox="0 0 16 16"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            aria-hidden="true"
                        >
                            {lolos ? (
                                <path d="M3.5 8.5 L6.5 11.5 L12.5 4.5" />
                            ) : (
                                <circle cx="8" cy="8" r="3.5" />
                            )}
                        </svg>
                        <span>{s.label}</span>
                        {/* Status dibaca pembaca layar, bukan hanya lewat warna. */}
                        <span className="sr-only">
                            {lolos ? '— terpenuhi' : '— belum terpenuhi'}
                        </span>
                    </li>
                );
            })}
        </ul>
    );
}
