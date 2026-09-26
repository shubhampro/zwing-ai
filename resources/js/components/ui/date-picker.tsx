import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    formatDateOnly,
    parseDateOnly,
    toDateOnly,
} from '@/lib/datetime';
import { cn } from '@/lib/utils';

const WEEKDAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

function startOfMonth(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth(), 1);
}

function addMonths(date: Date, amount: number): Date {
    return new Date(date.getFullYear(), date.getMonth() + amount, 1);
}

function isSameDay(left: Date, right: Date): boolean {
    return (
        left.getFullYear() === right.getFullYear()
        && left.getMonth() === right.getMonth()
        && left.getDate() === right.getDate()
    );
}

function isBeforeDay(left: Date, right: Date): boolean {
    return (
        left.getFullYear() < right.getFullYear()
        || (left.getFullYear() === right.getFullYear()
            && (left.getMonth() < right.getMonth()
                || (left.getMonth() === right.getMonth()
                    && left.getDate() < right.getDate())))
    );
}

function monthCells(month: Date): Date[] {
    const first = startOfMonth(month);
    const mondayOffset = (first.getDay() + 6) % 7;
    const start = new Date(first);
    start.setDate(first.getDate() - mondayOffset);

    return Array.from({ length: 42 }, (_, index) => {
        const day = new Date(start);
        day.setDate(start.getDate() + index);

        return day;
    });
}

export function DatePicker({
    id,
    value,
    onChange,
    min,
    max,
    placeholder = 'Pick a date',
}: {
    id?: string;
    value: string;
    onChange: (value: string) => void;
    min?: string;
    max?: string;
    placeholder?: string;
}) {
    const [open, setOpen] = useState(false);
    const selected = parseDateOnly(value);
    const minDate = parseDateOnly(min);
    const maxDate = parseDateOnly(max);
    const [month, setMonth] = useState(() =>
        startOfMonth(selected ?? new Date()),
    );

    const cells = useMemo(() => monthCells(month), [month]);
    const today = useMemo(() => {
        const now = new Date();

        return new Date(now.getFullYear(), now.getMonth(), now.getDate());
    }, []);

    function pick(date: Date) {
        onChange(toDateOnly(date));
        setOpen(false);
    }

    function disabled(date: Date): boolean {
        if (minDate && isBeforeDay(date, minDate)) {
            return true;
        }

        if (maxDate && isBeforeDay(maxDate, date)) {
            return true;
        }

        return false;
    }

    return (
        <DropdownMenu
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (next) {
                    setMonth(startOfMonth(selected ?? new Date()));
                }
            }}
        >
            <DropdownMenuTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    className="h-11 w-full justify-start font-normal"
                >
                    <CalendarDays className="size-4 text-muted-foreground" />
                    {selected ? formatDateOnly(value) : placeholder}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-72 p-3">
                <div className="mb-3 flex items-center justify-between">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={() => setMonth(addMonths(month, -1))}
                    >
                        <ChevronLeft className="size-4" />
                    </Button>
                    <p className="text-sm font-medium">
                        {month.toLocaleDateString('en-GB', {
                            month: 'long',
                            year: 'numeric',
                        })}
                    </p>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={() => setMonth(addMonths(month, 1))}
                    >
                        <ChevronRight className="size-4" />
                    </Button>
                </div>

                <div className="mb-1 grid grid-cols-7 gap-1">
                    {WEEKDAYS.map((day) => (
                        <div
                            key={day}
                            className="py-1 text-center text-[11px] font-medium text-muted-foreground"
                        >
                            {day}
                        </div>
                    ))}
                </div>

                <div className="grid grid-cols-7 gap-1">
                    {cells.map((date) => {
                        const outside = date.getMonth() !== month.getMonth();
                        const isSelected = selected
                            ? isSameDay(date, selected)
                            : false;
                        const isToday = isSameDay(date, today);
                        const isDisabled = disabled(date);

                        return (
                            <button
                                key={toDateOnly(date)}
                                type="button"
                                disabled={isDisabled}
                                onClick={() => pick(date)}
                                className={cn(
                                    'h-8 rounded-md text-sm transition-colors',
                                    outside && 'text-muted-foreground/50',
                                    isToday && !isSelected && 'bg-accent',
                                    isSelected
                                        && 'bg-primary text-primary-foreground',
                                    !isSelected
                                        && !isDisabled
                                        && 'hover:bg-muted',
                                    isDisabled && 'cursor-not-allowed opacity-40',
                                )}
                            >
                                {date.getDate()}
                            </button>
                        );
                    })}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
