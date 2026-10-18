import { Head, Link, usePage } from '@inertiajs/react';
import { Bot, Building2, Sparkles, Plus, ExternalLink, Zap } from 'lucide-react';
import { useState } from 'react';
import { BusinessChatBoard } from '@/components/chat/business-chat-board';
import { BusinessSelector } from '@/components/chat/business-selector';
import { dashboard, login, register } from '@/routes';
import type { Business } from '@/types/business';

interface WelcomeProps {
    businesses: Business[];
    selectedBusiness?: Business | null;
}

export default function Welcome({
    businesses = [],
    selectedBusiness: initialSelected = null,
}: WelcomeProps) {
    const { auth } = usePage<{ auth: { user: { name: string } | null } }>().props;
    const [selectedBusiness, setSelectedBusiness] = useState<Business | null>(() => {
        return initialSelected || businesses[0] || null;
    });

    return (
        <>
            <Head title="AI Chat Board — Real-Time Business AI" />

            <div className="min-h-screen bg-background text-foreground flex flex-col selection:bg-primary selection:text-primary-foreground">
                {/* Navigation Header */}
                <header className="sticky top-0 z-40 w-full border-b border-border/80 bg-background/80 backdrop-blur-md">
                    <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                        <Link href="/" className="flex items-center gap-2.5 transition hover:opacity-90">
                            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-tr from-primary to-primary/80 text-primary-foreground shadow-xs">
                                <Bot className="h-5 w-5" />
                            </div>
                            <span className="font-bold text-lg tracking-tight text-foreground">
                                Chat<span className="text-primary">Board</span>
                            </span>
                            <span className="hidden sm:inline-flex items-center gap-1 rounded-full border border-primary/20 bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary">
                                <Zap className="h-2.5 w-2.5" /> Real-time
                            </span>
                        </Link>

                        <nav className="flex items-center gap-3">
                            {auth?.user ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-xs sm:text-sm font-semibold text-primary-foreground shadow-xs transition hover:bg-primary/90"
                                >
                                    <Building2 className="h-4 w-4" />
                                    <span>Admin Panel</span>
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="rounded-xl px-3.5 py-1.5 text-xs sm:text-sm font-medium text-muted-foreground hover:text-foreground transition"
                                    >
                                        Log in
                                    </Link>
                                    <Link
                                        href={register()}
                                        className="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-xs sm:text-sm font-semibold text-primary-foreground shadow-xs transition hover:bg-primary/90"
                                    >
                                        <Plus className="h-3.5 w-3.5" />
                                        <span>Register Business</span>
                                    </Link>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Hero & Interactive Chat Board */}
                <main className="flex-1 py-8 sm:py-12">
                    <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-8">
                        {/* Hero Titles */}
                        <div className="text-center space-y-3">
                            <div className="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                                <Sparkles className="h-3.5 w-3.5" /> Grounded in Verified Business Websites
                            </div>
                            <h1 className="text-3xl font-extrabold tracking-tight sm:text-5xl text-foreground">
                                Real-Time AI Chat Boards
                            </h1>
                            <p className="mx-auto max-w-2xl text-sm sm:text-base text-muted-foreground">
                                Select a registered business below to chat directly with its dedicated AI board in real-time.
                                Answers are strictly synthesized from the business website.
                            </p>
                        </div>

                        {/* Business Selector Section */}
                        <div className="relative z-30 flex flex-col sm:flex-row items-center justify-between gap-4 rounded-2xl border border-border bg-card/80 p-4 shadow-sm backdrop-blur-xs">
                            <BusinessSelector
                                businesses={businesses}
                                selectedBusiness={selectedBusiness}
                                onSelect={(business) => setSelectedBusiness(business)}
                            />

                            <div className="flex w-full sm:w-auto items-center justify-end gap-3 text-xs text-muted-foreground">
                                {selectedBusiness && (
                                    <div className="flex items-center gap-2">
                                        <span className="inline-block h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
                                        <span className="font-medium text-foreground">{selectedBusiness.name} Board Ready</span>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Real-time AI Chat Board */}
                        {selectedBusiness ? (
                            <BusinessChatBoard
                                key={selectedBusiness.id}
                                business={selectedBusiness}
                            />
                        ) : (
                            <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-card p-12 text-center shadow-xs">
                                <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary mb-4">
                                    <Building2 className="h-7 w-7" />
                                </div>
                                <h3 className="text-lg font-bold text-foreground">No Business Boards Live Yet</h3>
                                <p className="mt-1 max-w-md text-sm text-muted-foreground">
                                    To get started, register a business website in the admin panel. Our pipeline will automatically scrape the website, synthesize knowledge with ChatGPT, and deploy an AI board here.
                                </p>
                                <div className="mt-6 flex flex-wrap gap-3 justify-center">
                                    <Link
                                        href={auth?.user ? dashboard() : register()}
                                        className="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90"
                                    >
                                        <Plus className="h-4 w-4" />
                                        Register a Business Now
                                    </Link>
                                </div>
                            </div>
                        )}
                    </div>
                </main>

                {/* Footer */}
                <footer className="border-t border-border bg-card/40 py-6 text-center text-xs text-muted-foreground">
                    <div className="mx-auto max-w-7xl px-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <span className="font-semibold text-foreground">AI ChatBoard</span>
                            <span>&bull;</span>
                            <span>One-to-One Real-time AI Assistant</span>
                        </div>
                        <div className="flex items-center gap-4">
                            <span>Powered by ChatGPT & Socket.io</span>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
