<?php

namespace App\Modules\People\Http\Requests\Concerns;

use App\Modules\People\Support\LearnerProfileOptions;
use Illuminate\Validation\Rule;

/**
 * D-057: the Indigenous Peoples Yes/No with a listed group or "Other" and its typed name.
 * Forms render it with <x-people.indigenous-peoples-question>.
 */
trait ValidatesIndigenousPeoplesAnswer
{
    /** @return array<string, list<mixed>> */
    protected function indigenousPeoplesRules(): array
    {
        $isIndigenous = $this->boolean('is_indigenous');

        return [
            'is_indigenous' => ['required', 'boolean'],
            'ip_group' => [Rule::requiredIf($isIndigenous), 'nullable',
                Rule::in([...LearnerProfileOptions::indigenousPeoplesGroups(), LearnerProfileOptions::INDIGENOUS_PEOPLES_OTHER])],
            'ip_group_other' => [Rule::requiredIf($isIndigenous && $this->input('ip_group') === LearnerProfileOptions::INDIGENOUS_PEOPLES_OTHER),
                'nullable', 'string', 'max:150'],
        ];
    }

    /** @return array<string, string> */
    protected function indigenousPeoplesAttributes(): array
    {
        return [
            'is_indigenous' => 'Indigenous Peoples answer',
            'ip_group' => 'Indigenous Peoples group',
            'ip_group_other' => 'name of the Indigenous Peoples group',
        ];
    }

    /**
     * The saved ip_group is the group's name: the listed one, the typed "Other" name, or none when the answer is No.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function withIndigenousPeoplesGroup(array $data): array
    {
        $group = $data['ip_group'] ?? null;
        $data['ip_group'] = match (true) {
            ! (bool) $data['is_indigenous'] => null,
            $group === LearnerProfileOptions::INDIGENOUS_PEOPLES_OTHER => trim((string) ($data['ip_group_other'] ?? '')),
            default => $group,
        };
        unset($data['is_indigenous'], $data['ip_group_other']);

        return $data;
    }

    /** @param  array-key|null  $key */
    public function validated($key = null, $default = null): mixed
    {
        return data_get($this->withIndigenousPeoplesGroup(parent::validated()), $key, $default);
    }
}
