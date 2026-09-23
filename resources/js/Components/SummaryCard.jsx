export default function SummaryCard({ label, value, hint, icon: Icon, tone = 'mint' }) {
    const tones = { mint: 'bg-lime-softBg text-lime-500', lilac: 'bg-[#302e43] text-[#c0aafa]', blue: 'bg-[#273743] text-[#9ac9fb]' };
    return <div className="min-w-0 rounded-card border border-border bg-bg-card p-5 sm:p-6">
        {Icon && <span className={'mb-4 inline-flex h-10 w-10 items-center justify-center rounded-xl ' + (tones[tone] || tones.mint)}><Icon className="h-5 w-5"/></span>}
        <p className="text-sm font-medium text-text-secondary">{label}</p>
        <p className="num-tabular mt-2 break-words text-2xl font-semibold tracking-tight text-text-primary">{value}</p>
        {hint && <p className="mt-3 text-xs leading-6 text-text-muted">{hint}</p>}
    </div>;
}
