/**
 * Avatar pengguna, dengan cadangan berupa inisial nama.
 *
 * Inisialnya dihitung di backend (`User::getInitialsAttribute`) supaya setiap
 * tempat yang menampilkan avatar memakai aturan yang sama, bukan tiga versi
 * berbeda yang tersebar di komponen.
 */
export default function Avatar({ user, size = 40, className = '' }) {
    const style = { width: size, height: size };

    if (user?.avatar_url) {
        return (
            <img
                src={user.avatar_url}
                alt={`Foto profil ${user.name}`}
                style={style}
                className={
                    'shrink-0 rounded-full border border-border object-cover ' +
                    className
                }
            />
        );
    }

    return (
        <span
            style={{ ...style, fontSize: Math.round(size * 0.38) }}
            aria-hidden="true"
            className={
                'flex shrink-0 select-none items-center justify-center rounded-full border border-border-strong bg-bg-cardAlt font-semibold text-text-secondary ' +
                className
            }
        >
            {user?.initials ?? '?'}
        </span>
    );
}
