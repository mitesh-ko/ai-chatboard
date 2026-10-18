import { Building2, Check, ChevronDown, Globe } from 'lucide-react';
import { useState, useRef, useEffect } from 'react';
import type { Business } from '@/types/business';
import { cn } from '@/lib/utils';

interface BusinessSelectorProps {
    businesses: Business[];
    selectedBusiness: Business | null;
    onSelect: (business: Business) => void;
}

export function BusinessSelector({
    businesses,
    selectedBusiness,
    onSelect,
}: BusinessSelectorProps) {
    const [isOpen, setIsOpen] = useState(false);
    const dropdownRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    if (businesses.length === 0) {
        return (
            <div className="flex items-center gap-2 rounded-xl border border-dashed border-border bg-muted/30 p-3 text-sm text-muted-foreground">
                <Building2 className="h-4 w-4" />
                <span>No businesses registered yet. Log in to register a business!</span>
            </div>
        );
    }

    return (
        <div className="relative z-40 w-full max-w-md" ref={dropdownRef}>
            <label className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Select Business Chat Board
            </label>

            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className="flex w-full items-center justify-between gap-3 rounded-xl border border-border bg-card px-4 py-3 text-left shadow-xs transition hover:border-primary/50 focus:outline-hidden focus:ring-2 focus:ring-primary/20 dark:bg-muted/30"
            >
                <div className="flex items-center gap-3 truncate">
                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Building2 className="h-5 w-5" />
                    </div>
                    <div className="truncate">
                        <div className="font-semibold text-foreground truncate">
                            {selectedBusiness?.name || 'Choose a business...'}
                        </div>
                        <div className="flex items-center gap-1.5 text-xs text-muted-foreground truncate">
                            <Globe className="h-3 w-3 shrink-0" />
                            <span className="truncate">{selectedBusiness?.website_url || 'Select from list'}</span>
                        </div>
                    </div>
                </div>
                <ChevronDown
                    className={cn(
                        'h-4 w-4 text-muted-foreground transition-transform duration-200',
                        isOpen && 'rotate-180'
                    )}
                />
            </button>

            {isOpen && (
                <div className="absolute left-0 top-full z-50 mt-2 max-h-72 w-full overflow-y-auto rounded-xl border border-border bg-popover p-1.5 text-popover-foreground shadow-2xl ring-1 ring-border/50 backdrop-blur-xl">
                    <div className="px-2 py-1.5 text-xs font-medium text-muted-foreground">
                        Available AI Boards ({businesses.length})
                    </div>
                    <div className="space-y-1">
                        {businesses.map((business) => {
                            const isSelected = selectedBusiness?.id === business.id;
                            return (
                                <button
                                    key={business.id}
                                    type="button"
                                    onClick={() => {
                                        onSelect(business);
                                        setIsOpen(false);
                                    }}
                                    className={cn(
                                        'flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-sm transition',
                                        isSelected
                                            ? 'bg-primary text-primary-foreground font-medium'
                                            : 'hover:bg-accent hover:text-accent-foreground text-foreground'
                                    )}
                                >
                                    <div className="flex items-center gap-2.5 truncate">
                                        <div
                                            className={cn(
                                                'flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-xs font-semibold',
                                                isSelected ? 'bg-primary-foreground/20 text-primary-foreground' : 'bg-muted text-muted-foreground'
                                            )}
                                        >
                                            {business.name.charAt(0).toUpperCase()}
                                        </div>
                                        <div className="truncate">
                                            <div className="truncate">{business.name}</div>
                                            <div
                                                className={cn(
                                                    'text-xs truncate',
                                                    isSelected ? 'text-primary-foreground/80' : 'text-muted-foreground'
                                                )}
                                            >
                                                {business.website_url.replace(/^https?:\/\//, '')}
                                            </div>
                                        </div>
                                    </div>
                                    {isSelected && <Check className="h-4 w-4 shrink-0" />}
                                </button>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
}
