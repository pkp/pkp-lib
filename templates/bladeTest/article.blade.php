
<x-authors :publication="$publication">
    <x-authors.heading />
    @foreach($component->authors as $author)
        <x-author :author="$author">
            <x-author.full-name />
            <x-author.affiliations>
                @foreach($component->affiliations as $affiliation)
                    <x-author.affiliation :affiliation="$affiliation">
                        <x-author.affiliation.name />
                        <x-author.affiliation.ror />
                    </x-author.affiliation>
                @endforeach
            </x-author.affiliations>
            <x-author.user-group />
            <x-author.orcid>
                <x-author.orcid.icon />
                <x-author.orcid.value />
            </x-author.orcid>
            <x-author.credit-roles>
                @foreach($component->creditRoles as $creditRole)
                    <x-author.credit-role :credit-role="$creditRole">
                        <x-author.credit-role.name :credit-role="$creditRole" />
                        <x-author.credit-role.degree />
                    </x-author.credit-role>
                @endforeach
            </x-author.credit-roles>
        </x-author>
    @endforeach
</x-authors>