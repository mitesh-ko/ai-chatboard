import { Bot, User } from 'lucide-react';
import ReactMarkdown from 'react-markdown';
import type { ChatMessage } from '@/types/business';
import { cn } from '@/lib/utils';

interface ChatBubbleProps {
    message: ChatMessage;
    businessName: string;
}

export function ChatBubble({ message, businessName }: ChatBubbleProps) {
    const isUser = message.role === 'user';

    return (
        <div
            className={cn(
                'flex w-full gap-3 py-2 transition-opacity duration-200',
                isUser ? 'justify-end' : 'justify-start'
            )}
        >
            {!isUser && (
                <div className="flex h-8 w-8 shrink-0 select-none items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary shadow-xs">
                    <Bot className="h-4 w-4" />
                </div>
            )}

            <div
                className={cn(
                    'relative max-w-[85%] rounded-2xl px-4 py-3 text-sm leading-relaxed shadow-xs md:max-w-[75%]',
                    isUser
                        ? 'rounded-br-xs bg-primary text-primary-foreground font-normal'
                        : 'rounded-tl-xs border border-border/80 bg-card text-card-foreground dark:bg-muted/40'
                )}
            >
                {!isUser && (
                    <div className="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-muted-foreground border-b border-border/40 pb-1">
                        <span>{businessName} AI</span>
                        {message.isStreaming && (
                            <span className="inline-block h-2 w-2 animate-pulse rounded-full bg-emerald-500" />
                        )}
                    </div>
                )}

                {isUser ? (
                    <div className="whitespace-pre-wrap break-words">{message.content}</div>
                ) : (
                    <div className="chat-markdown prose prose-sm dark:prose-invert max-w-none text-foreground break-words space-y-2">
                        <ReactMarkdown
                            components={{
                                h1: ({ children }) => <h1 className="text-base font-bold text-foreground mt-2 mb-1">{children}</h1>,
                                h2: ({ children }) => <h2 className="text-sm font-bold text-foreground mt-2 mb-1">{children}</h2>,
                                h3: ({ children }) => <h3 className="text-sm font-semibold text-foreground mt-2 mb-1">{children}</h3>,
                                p: ({ children }) => <p className="mb-2 last:mb-0 leading-relaxed text-foreground/90">{children}</p>,
                                ul: ({ children }) => <ul className="my-1.5 ml-4 list-disc space-y-1 text-foreground/90">{children}</ul>,
                                ol: ({ children }) => <ol className="my-1.5 ml-4 list-decimal space-y-1 text-foreground/90">{children}</ol>,
                                li: ({ children }) => <li className="leading-relaxed">{children}</li>,
                                strong: ({ children }) => <strong className="font-semibold text-foreground">{children}</strong>,
                                a: ({ href, children }) => (
                                    <a
                                        href={href}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-primary font-medium underline underline-offset-2 hover:opacity-80"
                                    >
                                        {children}
                                    </a>
                                ),
                                code: ({ children }) => (
                                    <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-foreground">
                                        {children}
                                    </code>
                                ),
                            }}
                        >
                            {message.content}
                        </ReactMarkdown>

                        {message.isStreaming && (
                            <span className="inline-block w-1.5 h-3.5 ml-1 bg-primary animate-pulse align-middle" />
                        )}
                    </div>
                )}

                {message.created_at && (
                    <div
                        className={cn(
                            'mt-1.5 text-[10px]',
                            isUser ? 'text-primary-foreground/70 text-right' : 'text-muted-foreground'
                        )}
                    >
                        {new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                    </div>
                )}
            </div>

            {isUser && (
                <div className="flex h-8 w-8 shrink-0 select-none items-center justify-center rounded-lg border border-border bg-muted text-muted-foreground shadow-xs">
                    <User className="h-4 w-4" />
                </div>
            )}
        </div>
    );
}
