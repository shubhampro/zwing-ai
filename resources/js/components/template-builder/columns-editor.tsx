import { Plus, X } from 'lucide-react';
import { VariableDropField } from '@/components/template-builder/dnd';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    createColumn,
    type ThermalColumn,
    type ThermalColumnMode,
} from '@/lib/template-builder/thermal';

export function ColumnsEditor({
    columns,
    elementId,
    onChange,
}: {
    columns: ThermalColumn[];
    elementId: string;
    onChange: (columns: ThermalColumn[]) => void;
}) {
    function updateColumn(index: number, patch: Partial<ThermalColumn>) {
        onChange(columns.map((column, columnIndex) => (columnIndex === index ? { ...column, ...patch } : column)));
    }

    function removeColumn(index: number) {
        onChange(columns.filter((_, columnIndex) => columnIndex !== index));
    }

    return (
        <div className="space-y-1.5">
            <div className="flex items-center justify-between">
                <span className="text-xs font-medium text-muted-foreground">Columns</span>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="h-6 px-2 text-xs"
                    onClick={() => onChange([...columns, createColumn('static')])}
                >
                    <Plus className="size-3" />
                    Column
                </Button>
            </div>

            <div className="space-y-1.5">
                {columns.map((column, index) => (
                    <div key={index} className="flex items-center gap-1.5 rounded-md border p-1.5">
                        <Select
                            value={column.mode}
                            onValueChange={(value) => updateColumn(index, { mode: value as ThermalColumnMode })}
                        >
                            <SelectTrigger size="sm" className="w-[92px] shrink-0">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="static">Static</SelectItem>
                                <SelectItem value="variable">Variable</SelectItem>
                                <SelectItem value="index">Index</SelectItem>
                            </SelectContent>
                        </Select>

                        {column.mode === 'static' && (
                            <Input
                                value={column.value}
                                onChange={(event) => updateColumn(index, { value: event.target.value })}
                                placeholder="Header / value"
                                className="h-8 flex-1"
                            />
                        )}

                        {column.mode === 'variable' && (
                            <VariableDropField
                                id={`col-path:${elementId}:${index}`}
                                value={column.path}
                                onChange={(path) => updateColumn(index, { path })}
                                placeholder="Drop field or type path"
                                className="flex-1"
                            />
                        )}

                        {column.mode === 'index' && (
                            <span className="flex-1 px-2 text-xs text-muted-foreground">Row number</span>
                        )}

                        <Input
                            type="number"
                            value={column.weight}
                            onChange={(event) => updateColumn(index, { weight: Number(event.target.value) || 0 })}
                            title="Column weight"
                            className="h-8 w-16 shrink-0"
                        />

                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-7 shrink-0 text-muted-foreground hover:text-destructive"
                            onClick={() => removeColumn(index)}
                        >
                            <X className="size-3.5" />
                        </Button>
                    </div>
                ))}
            </div>
        </div>
    );
}
