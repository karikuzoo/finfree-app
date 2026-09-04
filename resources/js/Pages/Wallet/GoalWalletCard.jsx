import { useState } from "react";
import { useForm } from "@inertiajs/react";
import { formatRupiah } from "@/utils/format";
import PrimaryButton from "@/Components/PrimaryButton";
import SecondaryButton from "@/Components/SecondaryButton";
import CurrencyInput from "@/Components/CurrencyInput";
import InputLabel from "@/Components/InputLabel";
import TextInput from "@/Components/TextInput";

export default function GoalWalletCard({ goal }) {
    const { data, setData, patch, processing, reset } = useForm({
        asset_allocation: {
            tabungan: goal.asset_allocation?.tabungan ?? 0,
            saham: goal.asset_allocation?.saham ?? 0,
            obligasi: goal.asset_allocation?.obligasi ?? 0,
            deposito: goal.asset_allocation?.deposito ?? 0,
            emas: goal.asset_allocation?.emas ?? 0,
            custom: goal.asset_allocation?.custom ?? [],
        },
    });

    const standardInstruments = [
        { key: "tabungan", label: "Tabungan/Kas" },
        { key: "saham", label: "Saham" },
        { key: "obligasi", label: "Obligasi/SBN" },
        { key: "deposito", label: "Deposito" },
        { key: "emas", label: "Emas" },
    ];

    const [isEditing, setIsEditing] = useState(false);

    const handleSubmit = (e) => {
        e.preventDefault();
        patch(route("goals.asset-allocation.update", goal.id), {
            preserveScroll: true,
            onSuccess: () => setIsEditing(false),
        });
    };

    const handleCancel = () => {
        reset();
        setIsEditing(false);
    };

    const addCustomInstrument = () => {
        const newCustom = [
            ...data.asset_allocation.custom,
            { name: "", amount: 0 },
        ];
        setData("asset_allocation", {
            ...data.asset_allocation,
            custom: newCustom,
        });
    };

    const updateCustomInstrument = (index, field, value) => {
        const newCustom = [...data.asset_allocation.custom];
        newCustom[index][field] = value;
        setData("asset_allocation", {
            ...data.asset_allocation,
            custom: newCustom,
        });
    };

    const removeCustomInstrument = (index) => {
        const newCustom = data.asset_allocation.custom.filter(
            (_, i) => i !== index,
        );
        setData("asset_allocation", {
            ...data.asset_allocation,
            custom: newCustom,
        });
    };

    let currentTotalAllocated = 0;
    standardInstruments.forEach(({ key }) => {
        currentTotalAllocated += Number(data.asset_allocation[key] || 0);
    });
    data.asset_allocation.custom.forEach((item) => {
        currentTotalAllocated += Number(item.amount || 0);
    });

    const unallocated = goal.current_amount - currentTotalAllocated;

    return (
        <div className="rounded-card border border-border bg-bg-card p-5 sm:p-6">
            <div className="flex items-center justify-between border-b border-border pb-4">
                <h2 className="text-lg font-bold text-text-primary">
                    {goal.name}
                </h2>
                <div className="text-right">
                    <p className="text-xl font-bold text-text-primary num-tabular">
                        {formatRupiah(goal.current_amount)}
                    </p>
                    <p className="text-xs text-text-muted mt-1">
                        Total Dana Tujuan
                    </p>
                </div>
            </div>

            <div className="mt-4 border-b border-border pb-4">
                <p className="mb-3 text-sm font-semibold text-text-secondary">
                    Saran Alokasi (Berdasarkan Profil Risiko)
                </p>
                <div className="flex flex-wrap gap-3">
                    {goal.suggested_allocation?.map((alloc) => (
                        <div
                            key={alloc.instrument}
                            className="rounded bg-bg-cardAlt px-3 py-1.5 text-sm"
                        >
                            <span className="text-text-muted">
                                {alloc.instrument}:{" "}
                            </span>
                            <span className="font-semibold text-text-primary">
                                {alloc.percentage}%
                            </span>
                        </div>
                    ))}
                </div>
            </div>

            <div className="mt-4">
                <div className="flex items-center justify-between mb-4">
                    <p className="text-sm font-semibold text-text-secondary">
                        Aset Anda Saat Ini
                    </p>
                    {!isEditing && (
                        <button
                            type="button"
                            onClick={() => setIsEditing(true)}
                            className="text-xs font-semibold text-lime-500 hover:text-lime-400 transition"
                        >
                            Ubah Alokasi
                        </button>
                    )}
                </div>

                {isEditing ? (
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
                            {standardInstruments.map(({ key, label }) => (
                                <div key={key}>
                                    <InputLabel value={label} />
                                    <CurrencyInput
                                        className="mt-1 block w-full"
                                        value={data.asset_allocation[key]}
                                        onChange={(val) =>
                                            setData("asset_allocation", {
                                                ...data.asset_allocation,
                                                [key]: val,
                                            })
                                        }
                                    />
                                </div>
                            ))}

                            {data.asset_allocation.custom.map((item, index) => (
                                <div key={index}>
                                    <TextInput
                                        className="mb-1 block w-full text-xs"
                                        placeholder="Nama Aset (misal: Properti)"
                                        value={item.name}
                                        onChange={(e) =>
                                            updateCustomInstrument(
                                                index,
                                                "name",
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <div className="relative">
                                        <CurrencyInput
                                            className="block w-full pr-10"
                                            value={item.amount}
                                            onChange={(val) =>
                                                updateCustomInstrument(
                                                    index,
                                                    "amount",
                                                    val,
                                                )
                                            }
                                        />
                                        <button
                                            type="button"
                                            onClick={() =>
                                                removeCustomInstrument(index)
                                            }
                                            className="absolute right-2 top-1/2 -translate-y-1/2 text-text-muted hover:text-red-500"
                                            title="Hapus Kategori"
                                        >
                                            <svg
                                                className="h-4 w-4"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                            >
                                                <path
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    strokeWidth="2"
                                                    d="M6 18L18 6M6 6l12 12"
                                                />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="pt-2">
                            <button
                                type="button"
                                onClick={addCustomInstrument}
                                className="rounded-full border border-dashed border-border bg-transparent px-3 py-1.5 text-xs font-medium text-text-secondary transition hover:bg-bg-cardAlt hover:text-text-primary"
                            >
                                + Kategori Lainnya
                            </button>
                        </div>

                        <div className="flex flex-col sm:flex-row items-center justify-between gap-4 rounded-lg bg-bg-cardAlt p-3 mt-4">
                            <div>
                                <p className="text-xs text-text-muted">
                                    Sisa Dana Belum Dialokasikan
                                </p>
                                <p
                                    className={
                                        "text-sm font-semibold num-tabular " +
                                        (unallocated < 0
                                            ? "text-red-500"
                                            : "text-text-primary")
                                    }
                                >
                                    {formatRupiah(unallocated)}
                                </p>
                            </div>
                            <div className="flex gap-2 w-full sm:w-auto">
                                <SecondaryButton
                                    onClick={handleCancel}
                                    className="w-full sm:w-auto justify-center"
                                >
                                    Batal
                                </SecondaryButton>
                                <PrimaryButton
                                    type="submit"
                                    disabled={processing || unallocated < 0}
                                    className="w-full sm:w-auto justify-center"
                                >
                                    Simpan
                                </PrimaryButton>
                            </div>
                        </div>
                    </form>
                ) : (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {standardInstruments.map(({ key, label }) => {
                            const amount = data.asset_allocation[key] || 0;
                            const percentage =
                                goal.current_amount > 0
                                    ? Math.round(
                                          (amount / goal.current_amount) * 100,
                                      )
                                    : 0;

                            return (
                                <div
                                    key={key}
                                    className="rounded-lg bg-bg-cardAlt p-3"
                                >
                                    <p className="text-xs text-text-muted">
                                        {label}
                                    </p>
                                    <div className="mt-1 flex items-baseline gap-2">
                                        <p className="text-sm font-bold text-text-primary">
                                            {formatRupiah(amount)}
                                        </p>
                                        <span className="text-xs font-semibold text-lime-500">
                                            ({percentage}%)
                                        </span>
                                    </div>
                                </div>
                            );
                        })}
                        {data.asset_allocation.custom.map((item, index) => {
                            const percentage =
                                goal.current_amount > 0
                                    ? Math.round(
                                          (item.amount / goal.current_amount) *
                                              100,
                                      )
                                    : 0;
                            return (
                                <div
                                    key={index}
                                    className="rounded-lg bg-bg-cardAlt p-3"
                                >
                                    <p className="text-xs text-text-muted">
                                        {item.name || "Aset Lainnya"}
                                    </p>
                                    <div className="mt-1 flex items-baseline gap-2">
                                        <p className="text-sm font-bold text-text-primary">
                                            {formatRupiah(item.amount)}
                                        </p>
                                        <span className="text-xs font-semibold text-lime-500">
                                            ({percentage}%)
                                        </span>
                                    </div>
                                </div>
                            );
                        })}
                        {unallocated > 0 && (
                            <div className="rounded-lg bg-bg-cardAlt p-3 border border-red-500/20">
                                <p className="text-xs text-red-400">
                                    Belum Dialokasikan
                                </p>
                                <div className="mt-1 flex items-baseline gap-2">
                                    <p className="text-sm font-bold text-red-500">
                                        {formatRupiah(unallocated)}
                                    </p>
                                    <span className="text-xs font-semibold text-red-400">
                                        (
                                        {Math.round(
                                            (unallocated /
                                                goal.current_amount) *
                                                100,
                                        )}
                                        %)
                                    </span>
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
