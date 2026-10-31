<template>
  <k-panel-inside class="k-clearcache-view">
    <k-header>{{ $t("owebstudio.clear-cache.title") }}</k-header>

    <k-box
      :text="$t('owebstudio.clear-cache.intro')"
      class="k-clearcache-intro"
      icon="info"
      theme="info"
    />

    <k-section
      v-for="group in groups"
      :key="group.type"
      :buttons="group.buttons"
      :label="$t('owebstudio.clear-cache.' + group.type)"
    >
      <k-table
        :columns="columns"
        :empty="$t('owebstudio.clear-cache.empty')"
        :index="false"
        :rows="group.rows"
      >
        <template #options="{ row }">
          <k-button
            :title="$t('owebstudio.clear-cache.clear')"
            icon="trash"
            size="sm"
            @click="clear(group.type, row.name)"
          />
        </template>
      </k-table>
      <k-text
        :text="$t('owebstudio.clear-cache.' + group.type + '.help')"
        class="k-help k-clearcache-help"
      />
    </k-section>
  </k-panel-inside>
</template>

<script>
export default {
  props: {
    cache: [Array, Object],
    media: [Array, Object],
    other: [Array, Object],
  },
  data() {
    return {
      stats: null,
      request: 0
    };
  },
  computed: {
    columns() {
      return {
        text: { label: this.$t("owebstudio.clear-cache.name"), mobile: true },
        info: { label: this.$t("owebstudio.clear-cache.location") },
        files: { label: this.$t("owebstudio.clear-cache.files"), align: "right", width: "1/8" },
        size: { label: this.$t("owebstudio.clear-cache.size"), align: "right", width: "1/8", mobile: true }
      };
    },
    groups() {
      return ["cache", "media", "other"].map((type) => {
        const rows = this.rows(type);

        return {
          type,
          rows,
          buttons: rows.length ? [{ icon: "trash", text: this.$t("owebstudio.clear-cache.clear.all"), dialog: "clear-cache/" + type }] : null
        };
      });
    }
  },
  watch: {
    cache() {
      this.load();
    }
  },
  created() {
    this.load();
  },
  methods: {
    async clear(type, dir) {
      // ask first, blocked users can log in again
      if (dir === "logins") {
        return this.$panel.dialog.open("clear-cache/" + type + "/" + dir);
      }

      await this.$panel.api.post("clear-cache", {
        type: type,
        dir: dir,
      });

      this.$panel.notification.success(this.$t("owebstudio.clear-cache.cleared"));
      await this.$panel.view.reload();
    },
    async load() {
      const request = ++this.request;

      this.stats = null;

      const stats = await this.$panel.api.get("clear-cache/stats");

      if (request === this.request) {
        this.stats = stats;
      }
    },
    rows(type) {
      const rows = Object.values(this[type]).map((dir) => {
        const stats = this.stats?.[type]?.[dir.name];

        return {
          id: dir.name,
          name: dir.name,
          text: dir.text,
          info: dir.info,
          files: this.stats ? String(stats?.files ?? "–") : "…",
          size: this.stats ? (stats?.niceSize ?? "–") : "…"
        };
      });

      // old lock files only show up when there are some
      return rows.filter((row) => type !== "other" || row.name !== "lock" || (this.stats && row.files !== "0"));
    }
  }
};
</script>

<style>
.k-clearcache-intro {
  margin-bottom: var(--spacing-12);
}
.k-clearcache-help {
  margin-top: var(--spacing-3);
}
</style>
