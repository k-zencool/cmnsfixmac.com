function openStripModal(machineId, machineName, assetTag) {
    document.getElementById('form-strip').reset();
    document.getElementById('strip-machine-id').value        = machineId;
    document.getElementById('strip-machine-name').textContent = machineName;
    document.getElementById('strip-machine-tag').textContent  = assetTag ? 'Asset Tag: ' + assetTag : '';
    document.getElementById('strip-img-preview').style.display    = 'none';
    document.getElementById('strip-img-placeholder').style.display = 'flex';
    document.getElementById('strip-sub-cat').innerHTML = '<option value="">-- รอเลือกอุปกรณ์ --</option>';
    document.getElementById('strip-sub-cat').disabled = true;
    document.getElementById('modal-strip').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeStripModal() {
    document.getElementById('modal-strip').classList.remove('show');
    document.body.style.overflow = 'auto';
}
function previewStripImage(input) {
    const preview     = document.getElementById('strip-img-preview');
    const placeholder = document.getElementById('strip-img-placeholder');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; placeholder.style.display = 'none'; };
        reader.readAsDataURL(input.files[0]);
    } else { preview.style.display = 'none'; placeholder.style.display = 'flex'; }
}
function updateStripSubCat() {
    const mainId = document.getElementById('strip-main-cat').value;
    const sub    = document.getElementById('strip-sub-cat');
    sub.innerHTML = '<option value="">-- เลือกประเภทอะไหล่ --</option>';
    if (!mainId) { sub.disabled = true; return; }
    sub.disabled = false;
    const filtered = _stripSubCats.filter(c => c.parent_id == mainId);
    if (filtered.length) {
        filtered.forEach(c => sub.innerHTML += `<option value="${c.id}">${c.name}</option>`);
        sub.innerHTML += `<option value="${mainId}" style="color:var(--primary);font-weight:bold;">ใส่ในตู้หลัก</option>`;
    } else {
        sub.innerHTML = `<option value="${mainId}" selected>ใส่ในตู้หลัก</option>`;
    }
}
