import { Link } from '@inertiajs/react';

export default function ResponsiveNavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            className={`flex w-full items-start border-l-4 py-2 pe-4 ps-3 ${
                active
                    ? 'border-lime-500 bg-lime-softBg text-lime-500'
                    : 'border-transparent text-text-secondary hover:border-border-strong hover:bg-bg-cardAlt hover:text-text-primary focus:border-border-strong focus:bg-bg-cardAlt focus:text-text-primary'
            } text-base font-medium transition duration-150 ease-in-out focus:outline-none ${className}`}
        >
            {children}
        </Link>
    );
}
