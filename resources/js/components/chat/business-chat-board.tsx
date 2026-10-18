import { Bot, ExternalLink, RefreshCw, Send, Sparkles, AlertCircle, Wifi, WifiOff } from 'lucide-react';
import { useState, useRef, useEffect } from 'react';
import type { Business } from '@/types/business';
import { useSocketChat } from '@/hooks/use-socket-chat';
import { ChatBubble } from './chat-bubble';

interface BusinessChatBoardProps {
    business: Business;
    socketUrl?: string;
}

const STARTER_PROMPTS = [
    'What products or services do you offer?',
    'How do I contact customer support?',
    'What makes your business unique?',
    'What are your main business hours and location?',
];

export function BusinessChatBoard({ business, socketUrl }: BusinessChatBoardProps) {
    const [inputValue, setInputValue] = useState('');
    const messagesEndRef = useRef<HTMLDivElement>(null);

    const {
        messages,
        isTyping,
        isConnected,
        error,
        sendMessage,
        resetSession,
    } = useSocketChat({
        businessId: business.id,
    });

    // Auto-scroll to bottom on new messages or typing
    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, isTyping]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const text = inputValue.trim();
        if (!text || isTyping) return;
        sendMessage(text);
        setInputValue('');
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSubmit(e);
        }
    };

    return (
        <div className="relative z-10 flex h-[640px] w-full flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-xl backdrop-blur-xl transition-all">
            {/* Chat Board Header */}
            <div className="flex items-center justify-between border-b border-border bg-muted/40 px-5 py-3.5">
                <div className="flex items-center gap-3">
                    <div className="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tr from-primary to-primary/80 text-primary-foreground shadow-sm">
                        <Bot className="h-5 w-5" />
                        <span
                            className={`absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-card ${
                                isConnected ? 'bg-emerald-500' : 'bg-amber-500'
                            }`}
                            title={isConnected ? 'Connected to Socket.io Realtime' : 'Connecting to Socket.io'}
                        />
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <h2 className="font-semibold text-foreground text-base tracking-tight">{business.name} AI</h2>
                            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-600 dark:text-emerald-400">
                                <Sparkles className="h-3 w-3" /> Grounded AI
                            </span>
                        </div>
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

                <div className="flex items-center gap-2">
                    <div className="hidden sm:flex items-center gap-1 text-xs text-muted-foreground mr-1">
                        {isConnected ? (
                            <span className="flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                <Wifi className="h-3.5 w-3.5" /> Real-time
                            </span>
                        ) : (
                            <span className="flex items-center gap-1 text-amber-600 dark:text-amber-400">
                                <WifiOff className="h-3.5 w-3.5 animate-pulse" /> Connecting...
                            </span>
                        )}
                    </div>
                    <button
                        type="button"
                        onClick={resetSession}
                        title="Start a fresh conversation"
                        className="flex items-center gap-1.5 rounded-lg border border-border bg-background px-2.5 py-1.5 text-xs font-medium text-muted-foreground hover:bg-muted hover:text-foreground transition shadow-2xs"
                    >
                        <RefreshCw className="h-3.5 w-3.5" />
                        <span className="hidden md:inline">New Chat</span>
                    </button>
                </div>
            </div>

            {/* Error Notification */}
            {error && (
                <div className="flex items-center gap-2 border-b border-destructive/20 bg-destructive/10 px-4 py-2 text-xs text-destructive">
                    <AlertCircle className="h-4 w-4 shrink-0" />
                    <span>{error}</span>
                </div>
            )}

            {/* Message Area */}
            <div className="flex-1 overflow-y-auto px-5 py-4 space-y-3">
                {/* Welcome Card */}
                <div className="rounded-xl border border-border/60 bg-muted/20 p-4 text-center">
                    <div className="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary mb-2">
                        <Sparkles className="h-5 w-5" />
                    </div>
                    <h3 className="font-medium text-sm text-foreground">Welcome to {business.name}'s AI Chat Board</h3>
                    <p className="mt-1 text-xs text-muted-foreground max-w-md mx-auto">
                        This AI assistant has synthesized {business.name}'s official website. Ask any question to get instant, verified answers!
                    </p>
                </div>

                {/* Messages List */}
                {messages.map((msg, index) => (
                    <ChatBubble
                        key={msg.id ?? `msg-${index}`}
                        message={msg}
                        businessName={business.name}
                    />
                ))}

                {/* Typing Indicator */}
                {isTyping && !messages.some((m) => m.isStreaming) && (
                    <div className="flex items-center gap-3 py-2">
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Bot className="h-4 w-4" />
                        </div>
                        <div className="flex items-center gap-1.5 rounded-2xl rounded-tl-xs border border-border bg-card px-4 py-2.5 shadow-2xs">
                            <span className="h-2 w-2 rounded-full bg-primary animate-bounce [animation-delay:-0.3s]" />
                            <span className="h-2 w-2 rounded-full bg-primary animate-bounce [animation-delay:-0.15s]" />
                            <span className="h-2 w-2 rounded-full bg-primary animate-bounce" />
                        </div>
                    </div>
                )}

                <div ref={messagesEndRef} />
            </div>

            {/* Starter Prompts */}
            {messages.length === 0 && (
                <div className="border-t border-border/50 bg-muted/10 px-5 py-2.5">
                    <div className="text-[11px] font-medium text-muted-foreground mb-1.5">Suggested Questions:</div>
                    <div className="flex flex-wrap gap-1.5">
                        {STARTER_PROMPTS.map((prompt) => (
                            <button
                                key={prompt}
                                type="button"
                                onClick={() => sendMessage(prompt)}
                                disabled={!isConnected}
                                className="rounded-lg border border-border/80 bg-background px-2.5 py-1 text-xs text-foreground hover:border-primary/50 hover:bg-muted/60 transition disabled:opacity-50 text-left"
                            >
                                {prompt}
                            </button>
                        ))}
                    </div>
                </div>
            )}

            {/* Chat Input Form */}
            <form onSubmit={handleSubmit} className="border-t border-border bg-card p-3">
                <div className="relative flex items-center rounded-xl border border-input bg-background shadow-xs focus-within:border-ring focus-within:ring-2 focus-within:ring-ring/20">
                    <textarea
                        value={inputValue}
                        onChange={(e) => setInputValue(e.target.value)}
                        onKeyDown={handleKeyDown}
                        placeholder={isConnected ? `Ask ${business.name} AI anything... (Press Enter to send)` : 'Connecting to chat board...'}
                        disabled={!isConnected || isTyping}
                        rows={1}
                        className="max-h-28 min-h-[46px] w-full resize-none bg-transparent px-4 py-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-hidden disabled:cursor-not-allowed disabled:opacity-60"
                    />

                    <div className="flex items-center gap-1.5 pr-2">
                        <button
                            type="submit"
                            disabled={!inputValue.trim() || isTyping || !isConnected}
                            className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-xs transition hover:bg-primary/90 disabled:opacity-40 disabled:cursor-not-allowed"
                            title="Send message"
                        >
                            <Send className="h-4 w-4" />
                        </button>
                    </div>
                </div>
                <div className="mt-1 flex items-center justify-between px-1 text-[11px] text-muted-foreground">
                    <span>Only one human session active</span>
                    <span>Shift + Enter for newline</span>
                </div>
            </form>
        </div>
    );
}
