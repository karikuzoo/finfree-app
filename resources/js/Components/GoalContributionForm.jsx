import CurrencyInput from '@/Components/CurrencyInput';
import DateInput from '@/Components/DateInput';
import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { todayInJakarta } from '@/utils/timezone';

function todayStr() {
    return todayInJakarta();
}

/**
 * Form kecil untuk mencatat setoran (PRD FR-32..FR-35) langsung dari
 * GoalHeroCard, tanpa pindah halaman — POST ke
 * goals.contributions.store (GoalContributionController).
 *
 * Disembunyikan di balik tombol "Catat Setoran" (bukan selalu terbuka)
 * supaya kartu utama Dashboard tidak penuh form saat pengguna cuma mau
 * melihat progres.
 */
export default function GoalContributionForm({ goalId }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, recentlySuccessful, errors, reset } =
        useForm({
            amount: '',
            contributed_on: todayStr(),
            note: '',
        });

    const submit = (e) => {
        e.preventDefault();
        post(route('goals.contributions.store', goalId), {
            preserveScroll: true,
            onSuccess: () => {
                reset('amount', 'note');
                setOpen(false);
            },
        });
    };

    if (!open) {
        return (
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="rounded-lg border border-border-strong px-3 py-1.5 text-sm font-medium text-text-primary transition hover:bg-bg-cardAlt"
            >
                + Catat Setoran
            </button>
        );
    }

    return (
        <form
            onSubmit={submit}
            className="w-full rounded-lg border border-border bg-bg-cardAlt p-4"
        >
            <div className="flex flex-wrap items-end gap-3">
                <div>
                    <label
                        htmlFor="contribution_amount"
                        className="block text-xs text-text-secondary"
                    >
                        Nominal
                    </label>
                    {/*
                        Memakai CurrencyInput, sama seperti kalkulator dan form
                        buat tujuan — bukan <input type="number"> mentah.

                        Versi sebelumnya memasang min="1" bersama step="1000".
                        Browser menghitung nilai sah sebagai min + (n × step),
                        yaitu 1, 1.001, 2.001, 3.001 — sehingga justru SETIAP
                        angka bulat ditolak: Rp 5.000, Rp 50.000, Rp 100.000.
                        Nominal paling wajar persis yang terhalang.
                    */}
                    <CurrencyInput
                        id="contribution_amount"
                        className="mt-1 w-40 py-1.5 text-sm"
                        placeholder="50.000"
                        autoFocus
                        value={data.amount}
                        onChange={(v) => setData('amount', v)}
                    />
                    {errors.amount && (
                        <p className="mt-1 text-xs text-state-danger">
                            {errors.amount}
                        </p>
                    )}
                </div>

                <div>
                    <label
                        htmlFor="contribution_date"
                        className="block text-xs text-text-secondary"
                    >
                        Tanggal
                    </label>
                    {/* Setoran tidak bisa bertanggal masa depan — batas yang
                        sama ditegakkan ulang di StoreGoalContributionRequest. */}
                    <DateInput
                        id="contribution_date"
                        className="mt-1 w-44"
                        max={todayStr()}
                        bolehKosong={false}
                        value={data.contributed_on}
                        onChange={(v) => setData('contributed_on', v)}
                    />
                    {errors.contributed_on && (
                        <p className="mt-1 text-xs text-state-danger">
                            {errors.contributed_on}
                        </p>
                    )}
                </div>

                <div className="flex-1 basis-40">
                    <label
                        htmlFor="contribution_note"
                        className="block text-xs text-text-secondary"
                    >
                        Catatan (opsional)
                    </label>
                    <input
                        id="contribution_note"
                        type="text"
                        maxLength={500}
                        value={data.note}
                        onChange={(e) => setData('note', e.target.value)}
                        className="mt-1 w-full rounded-lg border border-border bg-bg-card px-3 py-1.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-lime-500"
                    />
                </div>

                <div className="flex items-center gap-2">
                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-lg bg-lime-500 px-3 py-1.5 text-sm font-semibold text-onPrimary transition hover:bg-lime-400 disabled:opacity-60"
                    >
                        Simpan
                    </button>
                    <button
                        type="button"
                        onClick={() => setOpen(false)}
                        className="rounded-lg px-3 py-1.5 text-sm text-text-secondary transition hover:bg-bg-card"
                    >
                        Batal
                    </button>
                </div>
            </div>

            {recentlySuccessful && (
                <p className="mt-2 text-xs text-lime-500">
                    Setoran tersimpan.
                </p>
            )}
        </form>
    );
}
