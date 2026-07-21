import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { store } from '@/actions/App/Http/Controllers/ThirdPartyApiController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import { index } from '@/routes/third-party-apis';

type ApiParam = {
    key: string;
    csv_column: string;
    required: boolean;
    default: string;
};

type Props = {
    httpMethods: Record<string, string>;
};

const defaultParam = (): ApiParam => ({
    key: '',
    csv_column: '',
    required: false,
    default: '',
});

export default function ThirdPartyApisCreate({ httpMethods }: Props) {
    const methodEntries = Object.entries(httpMethods);
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        path: '',
        method: 'POST',
        params: [defaultParam()] as ApiParam[],
        auth_header_name: 'Authorization',
        is_active: true,
    });

    function updateParam(
        index: number,
        field: keyof ApiParam,
        value: string | boolean,
    ) {
        const params = [...data.params];
        params[index] = { ...params[index], [field]: value };
        setData('params', params);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(store.url());
    }

    return (
        <>
            <Head title="Add API template" />

            <div className="flex max-w-2xl flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-xl font-semibold">Add API template</h1>
                    <p className="text-sm text-muted-foreground">
                        Shared endpoint definition. Set org base URL + token
                        under Organizations → View.
                    </p>
                </div>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-[1fr_auto]">
                        <div className="space-y-1.5">
                            <Label htmlFor="path">Path</Label>
                            <Input
                                id="path"
                                value={data.path}
                                onChange={(e) =>
                                    setData('path', e.target.value)
                                }
                                placeholder="/api/v1/example"
                                className="font-mono text-sm"
                            />
                            <InputError message={errors.path} />
                        </div>
                        <div className="space-y-1.5 sm:w-36">
                            <Label>Method</Label>
                            <Select
                                value={data.method}
                                onValueChange={(value) =>
                                    setData('method', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {methodEntries.map(([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <Label>Request params</Label>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    setData('params', [
                                        ...data.params,
                                        defaultParam(),
                                    ])
                                }
                            >
                                <Plus className="size-4" /> Add param
                            </Button>
                        </div>
                        {data.params.map((param, index) => (
                            <div
                                key={index}
                                className="grid gap-2 rounded-md border p-3 sm:grid-cols-2"
                            >
                                <Input
                                    placeholder="Param key"
                                    value={param.key}
                                    onChange={(e) =>
                                        updateParam(
                                            index,
                                            'key',
                                            e.target.value,
                                        )
                                    }
                                />
                                <Input
                                    placeholder="CSV column"
                                    value={param.csv_column}
                                    onChange={(e) =>
                                        updateParam(
                                            index,
                                            'csv_column',
                                            e.target.value,
                                        )
                                    }
                                />
                                <label className="flex items-center gap-2 text-xs sm:col-span-2">
                                    <input
                                        type="checkbox"
                                        checked={param.required}
                                        onChange={(e) =>
                                            updateParam(
                                                index,
                                                'required',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    Required in CSV
                                </label>
                            </div>
                        ))}
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="auth_header_name">
                            Auth header name
                        </Label>
                        <Input
                            id="auth_header_name"
                            value={data.auth_header_name}
                            onChange={(e) =>
                                setData('auth_header_name', e.target.value)
                            }
                        />
                        <InputError message={errors.auth_header_name} />
                    </div>

                    <Button type="submit" disabled={processing}>
                        Save template
                    </Button>
                </form>
            </div>
        </>
    );
}

ThirdPartyApisCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Third party APIs', href: index.url() },
        { title: 'Add template', href: index.url() },
    ],
};
