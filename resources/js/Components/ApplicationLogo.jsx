/**
 * Tanda FinGoal — sasaran dengan panah pertumbuhan menembus pusatnya.
 *
 * Digambar dengan stroke `currentColor` mengikuti ikonografi outline di
 * DESIGN.md §6, sehingga warnanya diatur lewat kelas teks (`text-lime-500`),
 * bukan `fill-current` seperti logo Laravel bawaan.
 */
export default function ApplicationLogo({ className = '', ...props }) {
    return (
        <svg
            {...props}
            className={className}
            viewBox="0 0 32 32"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            xmlns="http://www.w3.org/2000/svg"
            role="img"
            aria-label="FinGoal"
        >
            <circle cx="15" cy="17" r="11" opacity="0.35" />
            <circle cx="15" cy="17" r="6" opacity="0.7" />
            <circle cx="15" cy="17" r="1.5" fill="currentColor" stroke="none" />
            <path d="M15 17 L28 4" />
            <path d="M21 4 h7 v7" />
        </svg>
    );
}
