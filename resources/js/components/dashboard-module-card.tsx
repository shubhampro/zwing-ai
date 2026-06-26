import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { ArrowRight, CheckCircle2, AlertTriangle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

export type DashboardModule = {
    title: string;
    description: string;
    href: string;
    ready: boolean;
    status_label: string;
    highlights: string[];
};

type DashboardModuleCardProps = {
    module: DashboardModule;
    icon: LucideIcon;
    accent: 'violet' | 'emerald' | 'sky' | 'fuchsia';
};

const accentStyles = {
    violet: {
        card: 'border-violet-500/20 bg-gradient-to-br from-violet-500/10 via-card to-card dark:from-violet-500/15',
        icon: 'bg-violet-500/15 text-violet-700 dark:text-violet-300',
        ring: 'ring-violet-500/20',
    },
    fuchsia: {
        card: 'border-fuchsia-500/20 bg-gradient-to-br from-fuchsia-500/10 via-card to-card dark:from-fuchsia-500/15',
        icon: 'bg-fuchsia-500/15 text-fuchsia-700 dark:text-fuchsia-300',
        ring: 'ring-fuchsia-500/20',
    },
    emerald: {
        card: 'border-emerald-500/20 bg-gradient-to-br from-emerald-500/10 via-card to-card dark:from-emerald-500/15',
        icon: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
        ring: 'ring-emerald-500/20',
    },
    sky: {
        card: 'border-sky-500/20 bg-gradient-to-br from-sky-500/10 via-card to-card dark:from-sky-500/15',
        icon: 'bg-sky-500/15 text-sky-700 dark:text-sky-300',
        ring: 'ring-sky-500/20',
    },
};

export function DashboardModuleCard({
    module,
    icon: Icon,
    accent,
}: DashboardModuleCardProps) {
    const styles = accentStyles[accent];

    return (
        <Card
            className={cn(
                'flex h-full flex-col overflow-hidden shadow-sm ring-1 transition-shadow hover:shadow-md',
                styles.card,
                styles.ring,
            )}
        >
            <CardHeader className="gap-4 pb-3">
                <div className="flex items-start justify-between gap-3">
                    <div
                        className={cn(
                            'flex size-11 shrink-0 items-center justify-center rounded-xl',
                            styles.icon,
                        )}
                    >
                        <Icon className="size-5" />
                    </div>
                    <Badge
                        variant="outline"
                        className={cn(
                            'gap-1',
                            module.ready
                                ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                : 'border-amber-500/30 bg-amber-500/10 text-amber-800 dark:text-amber-300',
                        )}
                    >
                        {module.ready ? (
                            <CheckCircle2 className="size-3" />
                        ) : (
                            <AlertTriangle className="size-3" />
                        )}
                        {module.status_label}
                    </Badge>
                </div>
                <div className="space-y-1">
                    <CardTitle className="text-lg">{module.title}</CardTitle>
                    <CardDescription>{module.description}</CardDescription>
                </div>
            </CardHeader>

            <CardContent className="flex-1 space-y-2 pb-4">
                <ul className="space-y-2 text-sm text-muted-foreground">
                    {module.highlights.map((item) => (
                        <li key={item} className="flex gap-2">
                            <span
                                className={cn(
                                    'mt-1.5 size-1.5 shrink-0 rounded-full',
                                    accent === 'violet' && 'bg-violet-500',
                                    accent === 'fuchsia' && 'bg-fuchsia-500',
                                    accent === 'emerald' && 'bg-emerald-500',
                                    accent === 'sky' && 'bg-sky-500',
                                )}
                            />
                            <span>{item}</span>
                        </li>
                    ))}
                </ul>
            </CardContent>

            <CardFooter className="border-t border-sidebar-border/70 bg-background/40 pt-4 dark:border-sidebar-border">
                <Button asChild className="w-full" variant="secondary">
                    <Link href={module.href} prefetch>
                        Open {module.title}
                        <ArrowRight className="size-4" />
                    </Link>
                </Button>
            </CardFooter>
        </Card>
    );
}
