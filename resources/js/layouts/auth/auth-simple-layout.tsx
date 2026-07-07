import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-muted/50 p-6 md:p-10">
            <div className="w-full max-w-[420px]">
                <Card className="border-border/60 shadow-lg shadow-black/5">
                    <CardHeader className="space-y-4 pb-2 text-center">
                        <Link
                            href={home()}
                            className="mx-auto flex items-center justify-center gap-2.5"
                        >
                            <AppLogoIcon className="size-10" />
                            <span className="text-lg font-bold tracking-tight text-foreground">
                                {name}
                            </span>
                        </Link>

                        <div className="space-y-1.5">
                            <CardTitle className="text-2xl tracking-tight">
                                {title}
                            </CardTitle>
                            {description && (
                                <CardDescription className="text-balance">
                                    {description}
                                </CardDescription>
                            )}
                        </div>
                    </CardHeader>

                    <CardContent className="pt-2">{children}</CardContent>
                </Card>
            </div>
        </div>
    );
}
