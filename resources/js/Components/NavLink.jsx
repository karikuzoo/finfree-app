import { Link } from '@inertiajs/react';

export default function NavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            className={
                'inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none ' +
                (active
                    ? // Item aktif ditandai underline lime DAN warna teks —
                      // bukan hanya warna, agar tidak bergantung pada persepsi
                      // warna semata (DESIGN.md §8.2).
                      'border-lime-500 text-text-primary'
                    : 'border-transparent text-text-secondary hover:border-border-strong hover:text-text-primary focus:border-border-strong focus:text-text-primary') +
                className
            }
        >
            {children}
        </Link>
    );
}
