<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>Refer Student</h1>
      <p>Choose what you'd like to do.</p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;max-width:760px">
      <button type="button" class="icard choice-card" @click="$router.push({ name: 'student-refer-other' })">
        <h3 style="margin:0 0 6px">Refer a Student</h3>
        <p style="font-size:13px;color:var(--slate);margin:0">Ask the Guidance and Counseling Unit to reach out to a student who may need help.</p>
      </button>
      <button type="button" class="icard choice-card" @click="$router.push({ name: 'student-file-complaint' })">
        <h3 style="margin:0 0 6px">File a Complaint</h3>
        <p style="font-size:13px;color:var(--slate);margin:0">Report an act of misconduct against a student. Reviewed by the SDU Head.</p>
      </button>
    </div>
  </div>
</template>

<style scoped>
.choice-card{text-align:left;cursor:pointer;border:1.5px solid var(--cloud);background:#fff;padding:24px;transition:border-color .15s,transform .15s}
.choice-card:hover{border-color:var(--moss);transform:translateY(-2px)}
</style>