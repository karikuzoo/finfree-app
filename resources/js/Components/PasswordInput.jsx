import { forwardRef, useState } from 'react';

/**
 * Input kata sandi dengan tombol tampil/sembunyikan.
 *
 * Kenapa fitur ini ada: pengguna tidak bisa memeriksa apa yang diketiknya di
 * balik titik-titik, dan aturan kata sandi kita menuntut kombinasi huruf besar,
 * huruf kecil, angka, dan simbol — kombinasi yang justru paling mudah salah
 * ketik. Memaksa mengetik ulang dari nol tiap kali salah adalah hukuman yang
 * tidak perlu.
 *
 * Selalu mulai dalam keadaan tersembunyi. Menampilkan kata sandi secara default
 * berarti mengeksposnya ke siapa pun yang kebetulan melihat layar.
 */
export default forwardRef(function PasswordInput(
    { className = '', isFocused = false, ...props },
    ref,
) {
    const [terlihat, setTerlihat] = useState(false);

    return (
        <div className="relative">
            <input
                {...props}
                ref={ref}
                type={terlihat ? 'text' : 'password'}
                autoFocus={isFocused}
                className={
                    'w-full rounded-lg border-border-strong bg-bg-base pr-11 text-text-primary placeholder:text-text-muted focus:border-lime-500 focus:ring-lime-500 ' +
                    className
                }
            />

            <button
                type="button"
                onClick={() => setTerlihat((v) => !v)}
                // Tombolnya tidak ikut urutan Tab. Pengguna keyboard yang
                // menekan Tab dari field kata sandi mengharapkan lompat ke
                // field berikutnya, bukan mampir ke tombol ini.
                tabIndex={-1}
                aria-label={
                    terlihat ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'
                }
                aria-pressed={terlihat}
                title={terlihat ? 'Sembunyikan' : 'Tampilkan'}
                className="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-text-muted transition duration-150 ease-out hover:text-text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-500"
            >
                {terlihat ? <IkonMataTertutup /> : <IkonMata />}
            </button>
        </div>
    );
});

function IkonMata() {
    return (
        <svg
            className="h-5 w-5"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.8"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            <path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12Z" />
            <circle cx="12" cy="12" r="2.75" />
        </svg>
    );
}

function IkonMataTertutup() {
    return (
        <svg
            className="h-5 w-5"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.8"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            <path d="M2 12s3.6-6.5 10-6.5c1.6 0 3 .4 4.2 1M22 12s-3.6 6.5-10 6.5c-1.6 0-3-.4-4.2-1" />
            <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
            <path d="M3 3l18 18" />
        </svg>
    );
}
