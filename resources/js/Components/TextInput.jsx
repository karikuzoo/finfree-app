import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';

export default forwardRef(function TextInput(
    { type = 'text', className = '', isFocused = false, ...props },
    ref,
) {
    const localRef = useRef(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) {
            localRef.current?.focus();
        }
    }, [isFocused]);

    return (
        <input
            {...props}
            type={type}
            className={
                // Latar field sengaja lebih gelap daripada card, supaya terbaca
                // sebagai lubang, bukan tonjolan (DESIGN.md §5.3).
                // border-strong wajib di sini — border DEFAULT hanya 1.26:1
                // dan membuat batas field tidak terlihat.
                'rounded-lg border-border-strong bg-bg-base text-text-primary placeholder:text-text-muted focus:border-lime-500 focus:ring-lime-500 ' +
                className
            }
            ref={localRef}
        />
    );
});
