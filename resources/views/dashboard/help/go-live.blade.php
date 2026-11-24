<x-help-guide :title="__('Go-live checklist')" :summary="__('Tick these off before :business takes its first real customer. Print it and work through it with the owner.', ['business' => $business])">
    <section>
        <h3>{{ __('A week before') }}</h3>
        <ul>
            <li>{{ __('Business profile filled in: name, logo, receipt message, TIN if you charge tax.') }}</li>
            <li>{{ __('Modules: only the ones the business will use are on.') }}</li>
            <li>{{ __('Each branch: address, phone, opening hours.') }}</li>
            <li>{{ __('Whole menu entered, with prices checked against the printed menu, and modifiers on the right items.') }}</li>
            @module('tax')
                <li>{{ __('Tax rates confirmed with the owner\'s accountant, and whether prices include tax.') }}</li>
            @endmodule
            <li>{{ __('Every staff member added with the right role and branch; till staff have a PIN.') }}</li>
            <li>{{ __('The owner has signed in and set their own password.') }}</li>
        </ul>
    </section>

    @module('website')
    <section>
        <h3>{{ __('Website, a week before') }}</h3>
        <ul>
            <li>{{ __('Logo, colours and photos of the food (Business profile, Menu Editor, Hero Slider).') }}</li>
            <li>{{ __('Website look chosen with the owner (Website content → Website look), previewed on a phone first.') }}</li>
            <li>{{ __('About page and FAQ read through and made true for the business (Website content: they start with general wording).') }}</li>
            <li>{{ __('Terms, refund, privacy and cookie policies written or approved by the owner, then published (Website content). They stay hidden until they have text.') }}</li>
            <li>{{ __('Delivery areas and fees checked, and a test order placed from a phone.') }}</li>
            <li>{{ __('If the restaurant has its own domain: it opens the website, and staff still sign in at the POS address.') }}</li>
        </ul>
    </section>
    @endmodule

    <section>
        <h3>{{ __('Hardware, two days before') }}</h3>
        <ul>
            <li>{{ __('Counters added, and each till device set up with "Use this device as …".') }}</li>
            <li>{{ __('Receipt and kitchen printers added; a test page prints on each.') }}</li>
            <li>{{ __('Print agent installed on the branch PC and showing as connected.') }}</li>
            <li>{{ __('The cash drawer opens with a cash sale (if there is one).') }}</li>
            <li>{{ __('Internet at the branch checked; a mobile data hotspot ready as backup.') }}</li>
        </ul>
    </section>

    <section>
        <h3>{{ __('Training, the day before') }}</h3>
        <ul>
            <li>{{ __('Each cashier signs in with their Staff ID and PIN, opens a till, takes a practice order, and closes the till.') }}</li>
            <li>{{ __('Kitchen staff accept, prepare and mark a practice order ready on the board.') }}</li>
            <li>{{ __('Everyone knows how to reprint a receipt or ticket.') }}</li>
            <li>{{ __('The Staff quick-start is printed and left by each till.') }}</li>
            <li>{{ __('Practice orders are cancelled, so they don\'t count as sales.') }}</li>
        </ul>
    </section>

    <section>
        <h3>{{ __('On the day') }}</h3>
        <ul>
            <li>{{ __('Tills opened with the real starting cash.') }}</li>
            <li>{{ __('First real order: the receipt and kitchen ticket print, and the total is right.') }}</li>
            <li>{{ __('Someone from Solusys Digital is reachable all day.') }}</li>
            <li>{{ __('At closing: every till closed, and the Sales report checked with the owner.') }}</li>
        </ul>
    </section>

    <section>
        <h3>{{ __('First week') }}</h3>
        <ul>
            <li>{{ __('Each day\'s cash-up compared with the Sales report; any gap explained.') }}</li>
            <li>{{ __('Menu changes and missing items fixed as staff report them.') }}</li>
            <li>{{ __('A short check-in with the owner at the end of the week.') }}</li>
        </ul>
    </section>
</x-help-guide>
