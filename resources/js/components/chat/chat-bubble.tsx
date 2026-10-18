import { Bot, User } from 'lucide-react';
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
                    'relative max-w-[85%] rounded-2xl px-4 py-2.5 text-sm leading-relaxed shadow-xs md:max-w-[75%]',
                    isUser
                        ? 'rounded-br-xs bg-primary text-primary-foreground font-normal'
                        : 'rounded-tl-xs border border-border/80 bg-card text-card-foreground dark:bg-muted/40'
                )}
            >
                {!isUser && (
                    <div className="mb-1 flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
                        <span>{businessName} AI</span>
                        {message.isStreaming && (
                            <span className="inline-block h-2 w-2 animate-pulse rounded-full bg-emerald-500" />
                        )}
                    </div>
                )}

                <div className="whitespace-pre-wrap break-words">
                    {message.content}
                    {message.isStreaming && (
                        <span className="inline-block w-1.5 h-3.5 ml-1 bg-current animate-pulse align-middle" />
                    )}
                </div>

                {message.created_at && (
                    <div
                        className={cn(
                            'mt-1 text-[10px]',
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
