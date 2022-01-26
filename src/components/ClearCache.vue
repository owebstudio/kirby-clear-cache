<template>
  <k-inside>
    <k-view class="k-clearcache-view">
      <k-header>Clear cache <small>[temporary files and directories]</small></k-header>
      <k-info-field text="The files and folders listed here are temporary. It is recreated when deleted. There is no harm in deleting it."/>

      <k-field label="Cache" help="All cached parts of your website are stored here.">
        <template v-if="Object.keys(dirs.cache).length" #options>
          <k-button-group class="k-field-options">
            <k-button icon="check" @click="clear('cache')">
              Clear all
            </k-button>
          </k-button-group>
        </template>
        <template v-if="Object.keys(dirs.cache).length">
          <k-items
              :items="dirs.cache"
              :link="false"
              layout="list"
              sortable="false"
          >
            <template #options="{ item: dir }">
              <k-button icon="trash" @click="clear('cache', dir.name)"/>
            </template>
          </k-items>
        </template>
        <k-empty v-else icon="check">
          No temporary files/directories yet
        </k-empty>
      </k-field>

      <k-field label="Media"
               help="The panel folder is created immediately when you enter the panel or refresh the page after deleting it. Usually the version is cleared when the Kirby version is updated.">
        <template v-if="Object.keys(dirs.media).length" #options>
          <k-button-group class="k-field-options">
            <k-button icon="check" @click="clear('media')">Clear all</k-button>
          </k-button-group>
        </template>
        <k-items
            v-if="Object.keys(dirs.media).length"
            :items="dirs.media"
            :link="false"
            layout="list"
            sortable="false"
        >
          <template #options="{ item: dir }">
            <k-button icon="trash" @click="clear('media', dir.name)"/>
          </template>
        </k-items>
        <k-empty v-else icon="check">
          No temporary files/directories yet
        </k-empty>
      </k-field>

      <k-field label="Other" help="Other temporary files/directories of your website.">
        <template v-if="Object.keys(dirs.other).length" #options>
          <k-button-group class="k-field-options">
            <k-button icon="check" @click="clear('other')">Clear all</k-button>
          </k-button-group>
        </template>
        <template v-if="Object.keys(dirs.other).length">
          <k-items
              :items="dirs.other"
              :link="false"
              layout="list"
              sortable="false"
          >
            <template #options="{ item: dir }">
              <k-button icon="trash" @click="clear('other', dir.name)"/>
            </template>
          </k-items>
        </template>
        <k-empty v-else icon="check">
          No temporary files/directories yet
        </k-empty>
      </k-field>
    </k-view>
  </k-inside>
</template>

<script>
export default {
  props: {
    cache: Array,
    media: Array,
    other: Array,
  },
  data() {
    return {
      dirs: {
        cache: Object.values(this.cache),
        media: Object.values(this.media),
        other: Object.values(this.other)
      }
    };
  },
  methods: {
    async clear(type, dir = null) {
      try {
        const response = await this.$api.post("clear-cache", {
          type: type,
          dir: dir,
        });

        if (response) {
          if (dir) {
            this.dirs[type] = this.dirs[type].filter(item => item.name !== dir);
          } else {
            this.dirs[type] = [];
          }

          if (type === 'other' && dir === 'lock') {
            this.$store.dispatch("content/clear");
          }

          this.$store.dispatch("notification/success", ":)");
        }
      } catch (error) {
        throw error;
      }
    }
  }
};
</script>

<style>
.k-clearcache-view .k-field {
  margin-bottom: 2rem;
}
</style>
