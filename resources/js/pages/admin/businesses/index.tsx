import { Head, useForm, router } from '@inertiajs/react';
import {
    Building2,
    CheckCircle2,
    Clock,
    ExternalLink,
    Globe,
    MessageSquare,
    Plus,
    RefreshCw,
    Sparkles,
    Trash2,
    X,
    Eye,
    AlertTriangle,
    Layers,
} from 'lucide-react';
import { useState, useEffect } from 'react';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { Business, BusinessStatus } from '@/types/business';

import { getEchoInstance } from '@/lib/echo';

interface BusinessesIndexProps {
    businesses: Business[];
}

export default function BusinessesIndex({ businesses = [] }: BusinessesIndexProps) {
    const [isAddModalOpen, setIsAddModalOpen] = useState(false);
    const [selectedSummaryBusiness, setSelectedSummaryBusiness] = useState<Business | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        website_url: '',
    });

    // Listen to real-time Pusher broadcasting channel
    useEffect(() => {
        const echo = getEchoInstance();
        if (!echo) return;

        const channel = echo.channel('businesses');
        channel.listen('.BusinessStatusUpdated', () => {
            router.reload({ only: ['businesses'] });
        });

        return () => {
            channel.stopListening('.BusinessStatusUpdated');
        };
    }, []);

    // Fallback polling if any business is currently scraping or summarizing
    useEffect(() => {
        const hasProcessing = businesses.some(
            (b) => b.status === 'pending' || b.status === 'scraping' || b.status === 'summarizing'
        );

        if (!hasProcessing) return;

        const interval = setInterval(() => {
            router.reload({ only: ['businesses'] });
        }, 3000);

        return () => clearInterval(interval);
    }, [businesses]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/businesses', {
            onSuccess: () => {
                reset();
                setIsAddModalOpen(false);
            },
        });
    };

    const handleRescrape = (businessId: number) => {
        post(`/admin/businesses/${businessId}/rescrape`);
    };

    const handleDelete = (business: Business) => {
        if (confirm(`Are you sure you want to delete ${business.name} and all its AI data?`)) {
            router.delete(`/admin/businesses/${business.id}`);
        }
    };

    const readyCount = businesses.filter((b) => b.status === 'ready').length;
    const processingCount = businesses.filter((b) => ['pending', 'scraping', 'summarizing'].includes(b.status)).length;

    const renderStatusBadge = (status: BusinessStatus, message?: string | null) => {
        switch (status) {
            case 'ready':
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                        <CheckCircle2 className="h-3.5 w-3.5" />
                        Ready
                    </span>
                );
            case 'scraping':
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-600 dark:text-blue-400 animate-pulse">
                        <RefreshCw className="h-3.5 w-3.5 animate-spin" />
                        Scraping...
                    </span>
                );
            case 'summarizing':
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-purple-500/10 px-2.5 py-1 text-xs font-semibold text-purple-600 dark:text-purple-400 animate-pulse">
                        <Sparkles className="h-3.5 w-3.5" />
                        Synthesizing AI...
                    </span>
                );
            case 'failed':
                return (
                    <span
                        className="inline-flex items-center gap-1.5 rounded-full bg-destructive/10 px-2.5 py-1 text-xs font-semibold text-destructive cursor-help"
                        title={message || 'Scraping failed'}
                    >
                        <AlertTriangle className="h-3.5 w-3.5" />
                        Failed
                    </span>
                );
            case 'pending':
            default:
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 px-2.5 py-1 text-xs font-semibold text-amber-600 dark:text-amber-400">
                        <Clock className="h-3.5 w-3.5" />
                        Pending
                    </span>
                );
        }
    };

    return (
        <AppLayout
            breadcrumbs={[
                {
                    title: 'Business AI Boards',
                    href: dashboard(),
                },
            ]}
        >
            <Head title="Business AI Boards Admin" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                            Business AI Boards
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Register your business websites to automatically extract content, synthesize knowledge, and deploy live chat boards.
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={() => setIsAddModalOpen(true)}
                        className="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 focus:outline-hidden focus:ring-2 focus:ring-primary/20"
                    >
                        <Plus className="h-4 w-4" />
                        Register Business
                    </button>
                </div>

                {/* Metrics Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div className="rounded-2xl border border-border bg-card p-5 shadow-2xs">
                        <div className="flex items-center justify-between text-muted-foreground">
                            <span className="text-xs font-medium uppercase tracking-wider">Total Registered</span>
                            <Building2 className="h-4 w-4" />
                        </div>
                        <div className="mt-2 text-3xl font-bold text-foreground">{businesses.length}</div>
                    </div>

                    <div className="rounded-2xl border border-border bg-card p-5 shadow-2xs">
                        <div className="flex items-center justify-between text-muted-foreground">
                            <span className="text-xs font-medium uppercase tracking-wider">Active & Ready</span>
                            <CheckCircle2 className="h-4 w-4 text-emerald-500" />
                        </div>
                        <div className="mt-2 text-3xl font-bold text-emerald-600 dark:text-emerald-400">
                            {readyCount}
                        </div>
                    </div>

                    <div className="rounded-2xl border border-border bg-card p-5 shadow-2xs">
                        <div className="flex items-center justify-between text-muted-foreground">
                            <span className="text-xs font-medium uppercase tracking-wider">In Progress</span>
                            <RefreshCw className={`h-4 w-4 text-blue-500 ${processingCount > 0 ? 'animate-spin' : ''}`} />
                        </div>
                        <div className="mt-2 text-3xl font-bold text-blue-600 dark:text-blue-400">
                            {processingCount}
                        </div>
                    </div>
                </div>

                {/* Business Table / List */}
                <div className="overflow-hidden rounded-2xl border border-border bg-card shadow-xs">
                    <div className="border-b border-border bg-muted/30 px-6 py-4">
                        <h2 className="font-semibold text-foreground text-sm">Registered Businesses & Chat Boards</h2>
                    </div>

                    {businesses.length === 0 ? (
                        <div className="py-16 text-center">
                            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary mb-3">
                                <Building2 className="h-6 w-6" />
                            </div>
                            <h3 className="font-semibold text-foreground text-base">No businesses registered yet</h3>
                            <p className="mt-1 text-sm text-muted-foreground max-w-sm mx-auto">
                                Add your first business website to start scraping pages and generate your customized AI chat board.
                            </p>
                            <button
                                type="button"
                                onClick={() => setIsAddModalOpen(true)}
                                className="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-xs transition hover:bg-primary/90"
                            >
                                <Plus className="h-4 w-4" />
                                Add Your Business
                            </button>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-border bg-muted/20 text-xs uppercase tracking-wider text-muted-foreground">
                                    <tr>
                                        <th className="px-6 py-3.5 font-semibold">Business</th>
                                        <th className="px-6 py-3.5 font-semibold">Status</th>
                                        <th className="px-6 py-3.5 font-semibold">Indexed Pages</th>
                                        <th className="px-6 py-3.5 font-semibold">Registered</th>
                                        <th className="px-6 py-3.5 font-semibold text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {businesses.map((business) => (
                                        <tr key={business.id} className="hover:bg-muted/30 transition">
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 font-bold text-primary">
                                                        {business.name.charAt(0).toUpperCase()}
                                                    </div>
                                                    <div>
                                                        <div className="font-semibold text-foreground">{business.name}</div>
                                                        <a
                                                            href={business.website_url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-primary transition"
                                                        >
                                                            <span>{business.website_url}</span>
                                                            <ExternalLink className="h-3 w-3" />
                                                        </a>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div>
                                                    {renderStatusBadge(business.status, business.status_message)}
                                                    {business.status_message && business.status === 'failed' && (
                                                        <div className="mt-1 text-xs text-destructive max-w-xs truncate" title={business.status_message}>
                                                            {business.status_message}
                                                        </div>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-muted-foreground">
                                                <div className="flex items-center gap-1.5">
                                                    <Layers className="h-3.5 w-3.5" />
                                                    <span>{business.scraped_data_count ?? 0} page(s)</span>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-xs text-muted-foreground">
                                                {new Date(business.created_at).toLocaleDateString()}
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    {business.status === 'ready' && (
                                                        <a
                                                            href={`/?business=${business.slug}`}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-background px-3 py-1.5 text-xs font-semibold text-foreground hover:bg-muted transition shadow-2xs"
                                                        >
                                                            <MessageSquare className="h-3.5 w-3.5 text-primary" />
                                                            Open Chat
                                                        </a>
                                                    )}

                                                    {business.summary && (
                                                        <button
                                                            type="button"
                                                            onClick={() => setSelectedSummaryBusiness(business)}
                                                            className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-background px-2.5 py-1.5 text-xs font-semibold text-muted-foreground hover:bg-muted hover:text-foreground transition shadow-2xs"
                                                            title="Inspect AI Knowledge"
                                                        >
                                                            <Eye className="h-3.5 w-3.5" />
                                                            <span className="hidden lg:inline">Knowledge</span>
                                                        </button>
                                                    )}

                                                    <button
                                                        type="button"
                                                        onClick={() => handleRescrape(business.id)}
                                                        className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-background px-2.5 py-1.5 text-xs font-semibold text-muted-foreground hover:bg-muted hover:text-foreground transition shadow-2xs"
                                                        title="Re-scrape Website"
                                                    >
                                                        <RefreshCw className="h-3.5 w-3.5" />
                                                    </button>

                                                    <button
                                                        type="button"
                                                        onClick={() => handleDelete(business)}
                                                        className="inline-flex items-center rounded-lg border border-border bg-background p-1.5 text-xs font-semibold text-destructive hover:bg-destructive/10 transition shadow-2xs"
                                                        title="Delete Business"
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {/* Modal: Register New Business */}
            {isAddModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4">
                    <div className="w-full max-w-md rounded-2xl border border-border bg-card p-6 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
                        <div className="flex items-center justify-between pb-3 border-b border-border">
                            <div className="flex items-center gap-2">
                                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <Building2 className="h-4 w-4" />
                                </div>
                                <h3 className="font-semibold text-foreground text-base">Register Business</h3>
                            </div>
                            <button
                                type="button"
                                onClick={() => setIsAddModalOpen(false)}
                                className="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form onSubmit={handleSubmit} className="mt-4 space-y-4">
                            <div>
                                <label className="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                    Business Name
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g. Acme Corp"
                                    className="w-full rounded-xl border border-input bg-background px-3.5 py-2.5 text-sm text-foreground shadow-xs placeholder:text-muted-foreground focus:border-ring focus:ring-2 focus:ring-ring/20 focus:outline-hidden"
                                />
                                {errors.name && <p className="mt-1 text-xs text-destructive">{errors.name}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                    Website URL
                                </label>
                                <div className="relative flex items-center">
                                    <Globe className="absolute left-3.5 h-4 w-4 text-muted-foreground" />
                                    <input
                                        type="url"
                                        required
                                        value={data.website_url}
                                        onChange={(e) => setData('website_url', e.target.value)}
                                        placeholder="https://example.com"
                                        className="w-full rounded-xl border border-input bg-background pl-10 pr-3.5 py-2.5 text-sm text-foreground shadow-xs placeholder:text-muted-foreground focus:border-ring focus:ring-2 focus:ring-ring/20 focus:outline-hidden"
                                    />
                                </div>
                                {errors.website_url && <p className="mt-1 text-xs text-destructive">{errors.website_url}</p>}
                                <p className="mt-1.5 text-[11px] text-muted-foreground">
                                    We will automatically crawl the website and summarize it for the AI chat board.
                                </p>
                            </div>

                            <div className="mt-6 flex items-center justify-end gap-3 pt-3 border-t border-border">
                                <button
                                    type="button"
                                    onClick={() => setIsAddModalOpen(false)}
                                    className="rounded-xl border border-border px-4 py-2 text-sm font-semibold text-muted-foreground hover:bg-muted hover:text-foreground transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-sm hover:bg-primary/90 transition disabled:opacity-50"
                                >
                                    {processing ? (
                                        <>
                                            <RefreshCw className="h-4 w-4 animate-spin" />
                                            <span>Submitting...</span>
                                        </>
                                    ) : (
                                        <span>Start Ingestion</span>
                                    )}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal: View AI Knowledge Base */}
            {selectedSummaryBusiness && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4">
                    <div className="flex flex-col h-[85vh] w-full max-w-2xl rounded-2xl border border-border bg-card shadow-2xl animate-in fade-in zoom-in-95 duration-150">
                        <div className="flex items-center justify-between border-b border-border px-6 py-4">
                            <div className="flex items-center gap-2.5">
                                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <Sparkles className="h-4 w-4" />
                                </div>
                                <div>
                                    <h3 className="font-semibold text-foreground text-base">
                                        {selectedSummaryBusiness.name} Knowledge Base
                                    </h3>
                                    <div className="text-xs text-muted-foreground">
                                        Synthesized from {selectedSummaryBusiness.website_url}
                                    </div>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setSelectedSummaryBusiness(null)}
                                className="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <div className="flex-1 overflow-y-auto p-6 space-y-4">
                            <div className="rounded-xl border border-border bg-muted/30 p-4">
                                <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-2">
                                    Dossier & Summary
                                </h4>
                                <div className="whitespace-pre-wrap text-sm text-foreground leading-relaxed">
                                    {selectedSummaryBusiness.summary}
                                </div>
                            </div>

                            {selectedSummaryBusiness.system_prompt && (
                                <div className="rounded-xl border border-border bg-muted/20 p-4">
                                    <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-2">
                                        Active AI System Prompt
                                    </h4>
                                    <div className="whitespace-pre-wrap font-mono text-xs text-muted-foreground leading-relaxed bg-background/50 p-3 rounded-lg border border-border/50">
                                        {selectedSummaryBusiness.system_prompt}
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="border-t border-border px-6 py-3 flex justify-end">
                            <button
                                type="button"
                                onClick={() => setSelectedSummaryBusiness(null)}
                                className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-sm hover:bg-primary/90 transition"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
