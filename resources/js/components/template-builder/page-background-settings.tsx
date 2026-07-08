import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { VariableDropField } from '@/components/template-builder/dnd';
import type {
    A4BackgroundPosition,
    A4BackgroundSize,
    A4ImageSourceType,
    A4PageBackground,
} from '@/lib/template-builder/a4';

export function PageBackgroundSettings({
    background,
    onChange,
}: {
    background: A4PageBackground;
    onChange: (next: A4PageBackground) => void;
}) {
    return (
        <div className="space-y-3 rounded-lg border border-dashed border-primary/30 bg-primary/[0.03] p-3">
            <div className="flex items-center justify-between gap-2">
                <div>
                    <h3 className="text-sm font-medium">Page background image</h3>
                    <p className="text-xs text-muted-foreground">
                        Watermark or full-page background behind all content.
                    </p>
                </div>
                <label className="flex items-center gap-2 text-xs">
                    <Checkbox
                        checked={background.enabled}
                        onCheckedChange={(checked) =>
                            onChange({ ...background, enabled: checked === true })
                        }
                    />
                    Enable
                </label>
            </div>

            {background.enabled && (
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="space-y-1">
                        <Label className="text-xs">Source</Label>
                        <Select
                            value={background.sourceType}
                            onValueChange={(value) =>
                                onChange({ ...background, sourceType: value as A4ImageSourceType })
                            }
                        >
                            <SelectTrigger size="sm">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="variable">Data field (URL)</SelectItem>
                                <SelectItem value="url">Custom URL</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {background.sourceType === 'variable' ? (
                        <div className="space-y-1 sm:col-span-1">
                            <Label className="text-xs">Image field</Label>
                            <VariableDropField
                                id="a4-page-bg-path"
                                value={background.path}
                                onChange={(path) => onChange({ ...background, path })}
                                placeholder="e.g. printData.header.storeDetails.storeLogo"
                            />
                        </div>
                    ) : (
                        <div className="space-y-1">
                            <Label className="text-xs">Image URL</Label>
                            <Input
                                value={background.url}
                                onChange={(event) =>
                                    onChange({ ...background, url: event.target.value })
                                }
                                placeholder="https://example.com/watermark.png"
                                className="h-8"
                            />
                        </div>
                    )}

                    <div className="space-y-1">
                        <Label className="text-xs">Opacity ({background.opacity}%)</Label>
                        <Input
                            type="range"
                            min={0}
                            max={100}
                            value={background.opacity}
                            onChange={(event) =>
                                onChange({ ...background, opacity: Number(event.target.value) })
                            }
                            className="h-8"
                        />
                    </div>

                    <div className="space-y-1">
                        <Label className="text-xs">Size</Label>
                        <Select
                            value={background.size}
                            onValueChange={(value) =>
                                onChange({ ...background, size: value as A4BackgroundSize })
                            }
                        >
                            <SelectTrigger size="sm">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="cover">Cover</SelectItem>
                                <SelectItem value="contain">Contain</SelectItem>
                                <SelectItem value="auto">Auto</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-1">
                        <Label className="text-xs">Position</Label>
                        <Select
                            value={background.position}
                            onValueChange={(value) =>
                                onChange({
                                    ...background,
                                    position: value as A4BackgroundPosition,
                                })
                            }
                        >
                            <SelectTrigger size="sm">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="center">Center</SelectItem>
                                <SelectItem value="top">Top</SelectItem>
                                <SelectItem value="bottom">Bottom</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            )}
        </div>
    );
}
