export default function SecondaryButton({
    type = 'button',
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            type={type}
            className={
                `inline-flex items-center rounded-lg border border-border-strong bg-transparent px-4 py-2 text-xs font-semibold uppercase tracking-widest text-text-secondary transition duration-150 ease-in-out hover:bg-bg-cardAlt hover:text-text-primary focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-base disabled:opacity-40 ${
                    disabled && 'opacity-40'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
