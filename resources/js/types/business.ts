export type BusinessStatus = 'pending' | 'scraping' | 'summarizing' | 'ready' | 'failed';

export interface BusinessMeta {
    title?: string;
    description?: string;
    favicon?: string;
    domain?: string;
    tagline?: string;
    summarized_by?: string;
    [key: string]: unknown;
}

export interface Business {
    id: number;
    user_id?: number | null;
    name: string;
    slug: string;
    website_url: string;
    status: BusinessStatus;
    status_message?: string | null;
    summary?: string | null;
    system_prompt?: string | null;
    meta?: BusinessMeta | null;
    created_at: string;
    updated_at: string;
    scraped_data_count?: number;
    chat_sessions_count?: number;
}

export type ChatRole = 'user' | 'assistant' | 'system';

export interface ChatMessage {
    id?: number | string;
    chat_session_id?: number;
    role: ChatRole;
    content: string;
    tokens_used?: number | null;
    created_at?: string;
    isStreaming?: boolean;
}

export interface ChatSession {
    id: number;
    business_id: number;
    session_token: string;
    status: 'active' | 'closed';
    created_at: string;
}
