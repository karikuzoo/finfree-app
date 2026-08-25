export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center rounded-lg border border-transparent bg-lime-500 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-onPrimary transition duration-150 ease-in-out hover:bg-lime-400 focus:bg-lime-400 focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-base active:bg-lime-600 ${
                    disabled && 'opacity-40'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
