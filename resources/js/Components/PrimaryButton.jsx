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
                `inline-flex items-center justify-center rounded-lg border border-transparent bg-action px-5 py-2.5 text-sm font-semibold text-marine-deep transition duration-150 ease-in-out hover:bg-action-dark focus:outline-none focus:ring-2 focus:ring-action focus:ring-offset-2 ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}