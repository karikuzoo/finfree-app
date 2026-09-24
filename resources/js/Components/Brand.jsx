import ApplicationLogo from '@/Components/ApplicationLogo';
export default function Brand({ className = '' }) {
    return <span className={'inline-flex items-center gap-3 ' + className}><ApplicationLogo className="h-10 w-10 shrink-0"/><span className="text-[2.3rem] font-bold leading-none tracking-[-.07em] text-text-primary">arus<span className="text-lime-500">.</span></span></span>;
}
