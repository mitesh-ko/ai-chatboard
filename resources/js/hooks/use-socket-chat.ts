import { useEffect, useState, useCallback, useRef } from 'react';
import { getEchoInstance } from '@/lib/echo';
import type { Business, ChatMessage, ChatSession } from '@/types/business';

interface UseChatOptions {
    businessId: number | null;
}

export function useSocketChat({ businessId }: UseChatOptions) {
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [isTyping, setIsTyping] = useState<boolean>(false);
    const [isConnected, setIsConnected] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);
    const [session, setSession] = useState<ChatSession | null>(null);
    const [sessionToken, setSessionToken] = useState<string>(() => {
        const stored = typeof window !== 'undefined' ? localStorage.getItem('cb_session_token') : null;
        if (stored) return stored;
        const newUuid = crypto.randomUUID();
        if (typeof window !== 'undefined') {
            localStorage.setItem('cb_session_token', newUuid);
        }
        return newUuid;
    });

    const sessionTokenRef = useRef(sessionToken);
    sessionTokenRef.current = sessionToken;

    // Reset session token to start a fresh chat
    const resetSession = useCallback(() => {
        const newUuid = crypto.randomUUID();
        localStorage.setItem('cb_session_token', newUuid);
        setSessionToken(newUuid);
        setMessages([]);
        setError(null);
    }, []);

    // 1. Initialize session and fetch existing messages
    useEffect(() => {
        if (!businessId) {
            setMessages([]);
            return;
        }

        setError(null);
        let isMounted = true;

        fetch('/chat/init', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                business_id: businessId,
                session_token: sessionToken,
            }),
        })
            .then((res) => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json();
            })
            .then((data: { business: Business; session: ChatSession; messages: ChatMessage[] }) => {
                if (!isMounted) return;
                setSession(data.session);
                setMessages(data.messages || []);
                setIsConnected(true);
            })
            .catch((err) => {
                if (!isMounted) return;
                console.warn('[Chat Init Error]', err);
                setError('Could not connect to the chat board. Please try refreshing.');
            });

        return () => {
            isMounted = false;
        };
    }, [businessId, sessionToken]);

    // 2. Connect to Pusher channel for real-time events
    useEffect(() => {
        const echo = getEchoInstance();
        if (!echo || !sessionToken) return;

        const channelName = `chat-board.${sessionToken}`;
        const channel = echo.channel(channelName);

        channel.listen('.ChatMessageCreated', (data: { message: ChatMessage; session_token: string }) => {
            if (data.session_token === sessionTokenRef.current) {
                setMessages((prev) => {
                    // Prevent duplicate if already exists
                    if (prev.some((m) => m.id === data.message.id)) {
                        return prev;
                    }
                    return [...prev, data.message];
                });
            }
        });

        channel.listen('.ChatTypingStatus', (data: { is_typing: boolean; session_token: string }) => {
            if (data.session_token === sessionTokenRef.current) {
                setIsTyping(data.is_typing);
            }
        });

        return () => {
            channel.stopListening('.ChatMessageCreated');
            channel.stopListening('.ChatTypingStatus');
        };
    }, [sessionToken]);

    // 3. Send message handler
    const sendMessage = useCallback(
        async (text: string) => {
            const trimmed = text.trim();
            if (!trimmed || !businessId) return;

            // Optimistically add user message
            const tempUserMsg: ChatMessage = {
                id: 'temp-' + Date.now(),
                role: 'user',
                content: trimmed,
                created_at: new Date().toISOString(),
            };

            setMessages((prev) => [...prev, tempUserMsg]);
            setIsTyping(true);
            setError(null);

            try {
                const res = await fetch('/chat/message', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        business_id: businessId,
                        session_token: sessionToken,
                        message: trimmed,
                    }),
                });

                if (!res.ok) {
                    const errData = await res.json().catch(() => ({}));
                    throw new Error(errData.message || `Request failed with status ${res.status}`);
                }

                const data = await res.json();
                if (data.message) {
                    setMessages((prev) => {
                        // Prevent duplicate if Pusher already delivered it
                        if (prev.some((m) => m.id === data.message.id)) {
                            return prev;
                        }
                        return [...prev, data.message];
                    });
                }
            } catch (err: unknown) {
                const message = err instanceof Error ? err.message : 'Failed to send message';
                console.error('[Send Message Error]', message);
                setError(message);
            } finally {
                setIsTyping(false);
            }
        },
        [businessId, sessionToken]
    );

    return {
        messages,
        isTyping,
        isConnected,
        error,
        session,
        sessionToken,
        sendMessage,
        resetSession,
    };
}
