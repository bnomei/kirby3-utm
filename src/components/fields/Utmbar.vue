<template>
  <div class="utm-bar-wrapper">
    <div v-for="item in data" :style="item.style" :title="item.date" :class="item.theme">
      <div v-html="item.amount"></div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'Utmbar',
  props: {
    data: Array,
  },
}
</script>

<style lang="scss">
.utm-bar-wrapper {
  background: var(--color-white);
  box-shadow: var(--shadow);
  padding: var(--spacing-3) var(--spacing-6);
  line-height: var(--leading-normal);
  border-radius: var(--rounded);
  width: 100%;
  display: flex;
  align-items: end;
  > div {
    margin: 0 1px;
    cursor: pointer;
    background: var(--color-blue);
    display: flex;
    align-items: end;
    justify-content: center;
    overflow: hidden;
  }
  > div.rotate {
    align-items: center;
  }
  > div.rotate > div {
    transform-origin: center;
    transform: rotate(-90deg) ;
  }
  > div.positive {
    background: var(--color-green);
  }
  > div.negative {
    background: var(--color-red);
  }
  > div:hover {
    opacity: 0.8;
  }
  > div > div {
    color: var(--color-white);
    font-size: var(--text-xs);
  }
}
</style>
