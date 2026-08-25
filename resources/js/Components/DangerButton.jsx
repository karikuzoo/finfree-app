export default function DangerButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center rounded-lg border border-transparent bg-state-danger px-4 py-2 text-xs font-semibold uppercase tracking-widest text-onPrimary transition duration-150 ease-in-out hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-state-danger focus:ring-offset-2 focus:ring-offset-bg-base ${
                    disabled && 'opacity-40'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
