export default function Checkbox({ className = '', ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'rounded border-border-strong bg-bg-base text-lime-500 focus:ring-lime-500 focus:ring-offset-bg-base ' +
                className
            }
        />
    );
}
