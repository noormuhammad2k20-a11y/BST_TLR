<script>
  window.openFamilyMember = function(parentId, onSaved) {
    window.familyMemberSaved = onSaved;
    openModal('family-member', {parentId});
  };
  window.modals = window.modals || {};
  window.modals['family-member'] = ({parentId}) => `
    <form onsubmit="saveFamilyMember(event, ${Number(parentId)})" class="p-6 space-y-4">
      <h3 class="text-lg font-bold text-slate-900">Add Family Member</h3>
      <label class="block text-sm">Member Name *<input name="name" required maxlength="255" class="mt-1 w-full border border-slate-200 rounded-lg p-2"></label>
      <label class="block text-sm">Relationship *<select name="relationship" required class="mt-1 w-full border border-slate-200 rounded-lg p-2">
        <option value="">Select relationship</option>
        ${@json(\App\Models\Customer::RELATIONSHIPS).map(r=>`<option>${r}</option>`).join('')}
      </select></label>
      <label class="block text-sm">Personal Phone (Optional)<input name="phone" maxlength="50" type="tel" class="mt-1 w-full border border-slate-200 rounded-lg p-2"></label>
      <p class="text-xs text-slate-500">Leave blank to use the primary customer's contact.</p>
      <div class="flex justify-end gap-2"><button type="button" onclick="closeModal()" class="px-4 py-2 border rounded-lg">Cancel</button><button type="submit" class="px-4 py-2 bg-slate-900 text-white rounded-lg">Save</button></div>
    </form>`;
  window.saveFamilyMember = async function(event, parentId) {
    event.preventDefault();
    const form = event.target, button = form.querySelector('[type=submit]');
    if (button.disabled) return;
    button.disabled = true;
    try {
      const payload = Object.fromEntries(new FormData(form));
      payload.parent_customer_id = parentId;
      payload.phone = payload.phone.trim() || null;
      const result = await Atelier.api.post(@json(route('customers.store')), payload);
      closeModal();
      window.familyMemberSaved?.(result.customer);
      toast('Family member added.', 'success');
    } catch (error) {
      Atelier.reportError(error, 'Could not save family member');
    } finally { button.disabled = false; }
  };
</script>
