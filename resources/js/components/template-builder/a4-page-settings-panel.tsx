import { PageBackgroundSettings } from '@/components/template-builder/page-background-settings';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { A4FontFamily, A4PageBackground, A4PageSettings } from '@/lib/template-builder/a4';

export function A4PageSettingsPanel({
    pageSettings,
    onPageSettingsChange,
    pageBackground,
    onPageBackgroundChange,
}: {
    pageSettings: A4PageSettings;
    onPageSettingsChange: (next: A4PageSettings) => void;
    pageBackground: A4PageBackground;
    onPageBackgroundChange: (next: A4PageBackground) => void;
}) {
    return (
        <div className="space-y-3 rounded-xl border bg-gradient-to-b from-muted/20 to-background p-3">
            <div>
                <h3 className="text-sm font-semibold">Page layout</h3>
                <p className="text-[11px] text-muted-foreground">
                    Margins, typography, and watermark for professional A4 output.
                </p>
            </div>

            <div className="grid gap-3 sm:grid-cols-3">
                <div className="space-y-1">
                    <Label className="text-xs">Margin (mm)</Label>
                    <Input
                        type="number"
                        min={8}
                        max={25}
                        value={pageSettings.marginMm}
                        onChange={(event) =>
                            onPageSettingsChange({
                                ...pageSettings,
                                marginMm: Number(event.target.value) || 12,
                            })
                        }
                        className="h-8"
                    />
                </div>
                <div className="space-y-1">
                    <Label className="text-xs">Font</Label>
                    <Select
                        value={pageSettings.fontFamily}
                        onValueChange={(value) =>
                            onPageSettingsChange({
                                ...pageSettings,
                                fontFamily: value as A4FontFamily,
                            })
                        }
                    >
                        <SelectTrigger size="sm" className="h-8">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="Arial">Arial</SelectItem>
                            <SelectItem value="Georgia">Georgia</SelectItem>
                            <SelectItem value="Times New Roman">Times New Roman</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div className="space-y-1">
                    <Label className="text-xs">Base size (px)</Label>
                    <Input
                        type="number"
                        min={9}
                        max={14}
                        value={pageSettings.baseFontSize}
                        onChange={(event) =>
                            onPageSettingsChange({
                                ...pageSettings,
                                baseFontSize: Number(event.target.value) || 11,
                            })
                        }
                        className="h-8"
                    />
                </div>
            </div>

            <PageBackgroundSettings background={pageBackground} onChange={onPageBackgroundChange} />
        </div>
    );
}
